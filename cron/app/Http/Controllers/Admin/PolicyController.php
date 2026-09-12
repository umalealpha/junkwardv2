<?php

namespace AlphaDirect\Http\Controllers\Admin;
use AlphaDirect\Http\Controllers\MobileApp\MobileAppController as MobC;
use AlphaDirect\Agency;
use AlphaDirect\AccountingRules;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use Illuminate\Support\Str;
use PDF;
use AlphaDirect\Archived_customer;
use AlphaDirect\Preinspection as Preinspections;
use AlphaDirect\ArchivedCustomerBanking;
use AlphaDirect\ArchivedCustomerProfile;
use AlphaDirect\ArchivedKYC;
use AlphaDirect\ArchivedPaymentTransactions;
use AlphaDirect\ArchivedPolicies;
use AlphaDirect\PolicyLedgers;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\ArchivedPolicyBeneficiary;
use AlphaDirect\ArchivedPolicyCellphone;
use AlphaDirect\ArchivedRealpayLogs;
use AlphaDirect\ArchivedRealpayPaymentRequest;
use AlphaDirect\ArchivedTransactions;
use AlphaDirect\ArchivedVCSNewTransaction;
use AlphaDirect\ArchivedVehicle;
use AlphaDirect\PolicyCoverages;
use AlphaDirect\City;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\Console\Commands\PolicyLedger;
use AlphaDirect\DiscountSurcharge;
use AlphaDirect\OfflinePaymentError;
use AlphaDirect\PolicyCoverCancelNote;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\PolicyStatusLogs;
use AlphaDirect\QuoteSettings;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\EarnPremium;
use AlphaDirect\State;
use AlphaDirect\Stores;
use AlphaDirect\PolicyTyreRim;
use AlphaDirect\VATChanges;
use AlphaDirect\Models\Kycclone;
use DateTime;
use AlphaDirect\BankBranches;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\RealpayLogs;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\RealpayContractInstallments;
use Illuminate\Database\Eloquent\Model;
use Intervention\Image\ImageManagerStatic as Image;
use Validator;
use AlphaDirect\AccidentDriver;
use AlphaDirect\Accounts;
use AlphaDirect\Actions;
use AlphaDirect\Activation;
use AlphaDirect\sentPolicyDocumentLogs;
use AlphaDirect\Banks;
use AlphaDirect\CancelVCSTransactionLog;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimAccidentPassenger;
use AlphaDirect\ClaimLife;
use AlphaDirect\ClaimReserves;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\ClaimSubType;
use AlphaDirect\ClaimThirdParty;
use AlphaDirect\ClaimVehicle;
use AlphaDirect\Country;
use AlphaDirect\PolicyBundled;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Documents;
use AlphaDirect\Exports\PolicyPaymentStatusDumpExport;
use AlphaDirect\FactorMain;
use AlphaDirect\FactorSubType;
use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Payment\OrangeMoney\OrangeMoneyController;
use AlphaDirect\Http\Controllers\Payment\RealPay\RealPayController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use AlphaDirect\Http\Controllers\Payment\VCS\VcsController;
use AlphaDirect\Http\Controllers\FrontendPay\CustomerController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\KYC;
use AlphaDirect\Ledger;
use AlphaDirect\Lookup;
use AlphaDirect\PaymentUrls;
use AlphaDirect\PaymentVendor;
use AlphaDirect\Policy;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCoverage;
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyLeads;
use AlphaDirect\PolicyLeadsFactor;
use AlphaDirect\sentPolicyDocuments;
use AlphaDirect\PolicyMember;
use AlphaDirect\PolicyMotorItems;
use AlphaDirect\PolicyPaymentStatusDump;
use AlphaDirect\Product;
use AlphaDirect\ProductCoverage;
use AlphaDirect\Productplan;
use AlphaDirect\ProductType;
use AlphaDirect\RecipientKyc;
use AlphaDirect\Region;
use AlphaDirect\Role;
use AlphaDirect\SmsLogs;
use AlphaDirect\Supplier;
use AlphaDirect\Transaction;
use AlphaDirect\Transactionsubtype;
use AlphaDirect\Transactiontype;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\vehicleOld;
use AlphaDirect\VehicleMake;
use Auth;
use AlphaDirect\OTP;
use AlphaDirect\VcsNewTransaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DB;
use GuzzleHttp\Exception\ClientException;
use Hash;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Mail;
use AlphaDirect\Mail\KycComplianceEmail;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Mail\SendMail;
use AlphaDirect\Mail\SendPolicyMail;
use AlphaDirect\SubLedger;
use Illuminate\Cache\NullStore;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\Repositories\PolicyCellPhone\PolicyCellPhoneInterface;
use AlphaDirect\Repositories\Customer\CustomerInterface;
use AlphaDirect\Repositories\CustomerProfile\CustomerProfileInterface;
use Log;
use AlphaDirect\Events\CommissionPolicyEvent;
use AlphaDirect\Events\DecrementCounterIfPolicySellsEvent as DecrementCounter;
use AlphaDirect\Events\RenewPolicySchedulesEvent;
use AlphaDirect\Quote;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\CustomerMati;
use AlphaDirect\PolicyAttachments;
use AlphaDirect\ClaimRecoveryInvolved;
use AlphaDirect\Coverage;
use AlphaDirect\Models\PolicyDiscountSurcharge;
use AlphaDirect\Models\SMSEmailLogs;
use Illuminate\Support\Arr;
use AlphaDirect\OtherPartyInsured;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\Events\CancelTokenEvent;
use AlphaDirect\Events\CancelScheduleTransactionEvent;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Http\Controllers\admin\DiscountSurchargeController;
use AlphaDirect\Http\Controllers\Admin\RealPayController as AdminRealPayController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Controllers\NgeniusPaymentController;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Models\OneTimePaymentURL;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\Http\Requests\CancelPolicyRequest;
use Modules\Incentive;
use AlphaDirect\KycCompliance;
use AlphaDirect\KycFields;
use AlphaDirect\Mail\CreditNoteMail;
use AlphaDirect\Mail\MatiLink;
use AlphaDirect\Mail\RenewPolicy;
use AlphaDirect\Mail\ReratedPremiumPayMail;
use AlphaDirect\Models\ClaimLegal;
use AlphaDirect\Models\CreditNote;
use AlphaDirect\Models\GetPolicyDocuments;
use AlphaDirect\Models\PolicyDocument;
use AlphaDirect\PolicyTerm;
use Modules\Incentive\Entities\Policies;
use phpDocumentor\Reflection\Types\Null_;
use AlphaDirect\Models\PolicyLifecycle;
use AlphaDirect\Models\Preinspection;
use AlphaDirect\PolicyActivateCancelledDate;
use AlphaDirect\PolicyReinstate;
use Respect\Validation\Rules\CreditCard;
use function PHPSTORM_META\type;
use AlphaDirect\Models\PolicyDocuments;
use AlphaDirect\Models\DpoTransaction;
use AlphaDirect\Models\LedgerArchive as ModelsLedgerArchive;
use AlphaDirect\Models\NgeniusTransection;
use AlphaDirect\Models\SubledgerArchive;
use AlphaDirect\PaymentActivityLog;
use AlphaDirect\PolicyPremiumLogs;
use Rap2hpoutre\LaravelLogViewer\Level;

class PolicyController extends Controller
{
    /*
     * Pass data through ajax call
     * for account view tab under policy edit
     * param: policy id ($id)
     * @return mixed
     */

    public function ledgerAccountView($id)
    {
        $ledgerData = Ledger::with('policy.customer')->where('policy_id', $id)
        ->where(function ($query) {
            $query->whereNotNull('invoice_file')->orWhereNotNull('credit')->orWhere('trans_type', 'Refund');
        })->get();

        $ledgerArchive = ModelsLedgerArchive::with('policy.customer')->where('policy_id', $id)
        ->where(function ($query) {
            $query->whereNotNull('invoice_file')->orWhereNotNull('credit')->orWhere('trans_type', 'Refund');
        })->get();

        $merged = $ledgerArchive->merge($ledgerData);
        $ledger = $merged->all();

        // $ledger = Ledger::with('policy.customer')->where('policy_id', $id)
        //     ->where(function ($query) {
        //         $query->whereNotNull('invoice_file')->orWhereNotNull('credit')->orWhere('trans_type', 'Refund');
        //     })->get();

        return DataTables::of($ledger)
            ->addColumn('customer_name', function ($ledger) {
                return $ledger->policy->customer->firstName . ' ' . $ledger->policy->customer->lastName;
            })
            ->editColumn('trans_ref', function ($ledger) {
                if ($ledger->invoice_amount != null) {
                    return $ledger->invoice_no;
                } else {
                    return $ledger->trans_ref;
                }
            })
            ->editColumn('credit', function ($ledger) {
                if ($ledger->credit == 0) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-success">P' . $ledger->credit . '</span>';
                    return $cred;
                }
            })
            ->editColumn('debit', function ($ledger) {
                if ($ledger->debit == 0) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-danger">P' . $ledger->debit . '</span>';
                    return $cred;
                }
            })
            ->editColumn('balance', function ($ledger) {
                if ($ledger->balance == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    // if ($ledger->balance < 0) {
                    //     $cred = '<span class="kt-font-bold kt-font-primary">- P' . number_format(abs($ledger->balance), 2, '.', '') . '</span>';
                    // } else {
                        $cred = '<span class="kt-font-bold kt-font-primary">P' . number_format(abs($ledger->balance), 2, '.', '') . '</span>';
                    //}

                    return $cred;
                }
            })
            //->editColumn('orig_trans', function ($ledger) {
            //    $orig_trans = Actions::where('id', $ledger->orig_trans)->first();
            //    if ($orig_trans != null) {
            //        return $orig_trans->name;
            //    } else {
            //        return null;
            //    }
            //})
            ->editColumn('unallocated', function ($ledger) {
                if ($ledger->unallocated == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';

                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-primary">P' . $ledger->unallocated . '</span>';
                    return $cred;
                }
            })
            ->rawColumns(['amount', 'status', 'trans_ref', 'actions', 'credit', 'customer_id', 'pmts_adjust', 'debit', 'unallocated', 'balance', 'other_charges', 'due_amount'])
            ->make(true);
    }
    public function earned_premiumsView($id)
    {
        $Earnpremium = EarnPremium::where('policy_id', $id)->get();
        return DataTables::of($Earnpremium)

            ->editColumn('trans_type', function ($Earnpremium) {
                if ($Earnpremium->trans_type != null) {
                    return $Earnpremium->trans_type =$Earnpremium->trans_type;
                } else {
                    return $Earnpremium->trans_type = 'N/A';
                }
            })
            ->editColumn('day_premium', function ($Earnpremium) {
                if ($Earnpremium->day_premium == 0) {
                    $day_premium = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $day_premium;
                } else {
                    $day_premium = '<span class="kt-font-bold kt-font-success">P' . $Earnpremium->day_premium . '</span>';
                    return $day_premium;
                }
            })
            ->editColumn('written_preminum', function ($Earnpremium) {
                if ($Earnpremium->written_preminum == 0) {
                    $written_preminum = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $written_preminum;
                } else {
                    $written_preminum = '<span class="kt-font-bold kt-font-danger">P' . $Earnpremium->written_preminum . '</span>';
                    return $written_preminum;
                }
            })
            ->editColumn('accounting_date', function ($Earnpremium) {
                if ($Earnpremium->start_date == 0) {
                    $accounting_date = '<span class="kt-font-bold kt-font-primary">N/A</span>';
                    return $accounting_date;
                } else {
                    $accounting_date = Carbon::parse($Earnpremium->start_date)->format('d-m-Y');
                    return $accounting_date;
                }
            })
            ->editColumn('days', function ($Earnpremium) {

                    $days = '<span class="kt-font-bold kt-font-primary">365</span>';
                    return $days;


            })


            ->rawColumns(['days', 'accounting_date', 'trans_type', 'written_preminum', 'day_premium'])
            ->make(true);
    }

    public function generateTokenForUrl($length=6){
        $pool = '023456789ABCDEFGHiJKLMNOPQRSTUVWXYZ';
        $token = substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
        $check = OneTimePaymentURL::where('token', $token)->count();
        while ($check > 0) {
            $token = substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
            if($length == 15)
                $check = OneTimePaymentURL::where('referenceNumber', $token)->count();
            else
                $check = OneTimePaymentURL::where('token', $token)->count();
        }

        return base64_encode($token);
    }

    public function generateSendPaymentURL($policyNumber,$paymentMethod=null,$type='renew',$amount){
        try{
            $policyData = Policy::where('policyNumber',$policyNumber)->first(['created_at','term_id','id']);
            if($policyData->term_id){
                $term = PolicyTerm::where('id',$policyData->term_id)->first();
                if(isset($term) != null){
                    $expiryDate = Carbon::parse($term->term_end_date)->addDays(15)->format('d-m-Y');
                }else{
                    $expiryDate = Carbon::parse($policyData->created)->addDays(15)->format('d-m-Y');
                }
            }else{
                $expiryDate = Carbon::parse($policyData->created)->addDays(15)->format('d-m-Y');
            }

            $payUrl = env('PAY_URL') != null ? env('PAY_URL') : $_ENV['PAY_URL'];
            $token = $this->generateTokenForUrl(6);
            $data = new OneTimePaymentURL();
            $data->policyNumber = $policyNumber;
            $data->paymentMethod = $paymentMethod;
            $data->token = $token;
            $data->amount = $amount;
            $data->link_type = $type;
            $data->expiry_date = $expiryDate;
            $data->referenceNumber = base64_decode($this->generateTokenForUrl(15));
            $data->link = $payUrl.$type.'/'.$token;
            //$data->link = app('bitly')->getUrl($payUrl.'/'.$str1.'/'.$str2);
            $data->status = 0;
            $data->save();

            return  $data->link;

        }catch (\Exception $ex){
            return null;
        }
    }

    public function getPolicyInformationReinstate(Request $request){
        try{
            if($request->get('token')){
                $token = base64_decode($request->get('token'));
                if($token){
                    $getInfo = OneTimePaymentURL::where('token',$request->get('token'))->first();
                    $linkInfo = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->token)->first();
                    $frequency = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->token)->value('frequency');
                    $paymentUrlInfo = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/reinstate_fresh/'.$request->token)
                                    ->orWhere('url', 'https://pay.alphadirect.co.bw/reinstate_arrears/'.$request->token)->first();
                    if($getInfo){
                        $policyInfo = Policy::where('policyNumber',$getInfo->policyNumber)->first(array('policyNumber','id','customer_id','premium_freq','product_id','status','sum_assured','premium','quoteNumber'));
                        if($policyInfo){
                            $customer = Customer::join('customer_profile','customer_profile.customer_id','=','customer.id')
                                ->where('customer.id',$policyInfo->customer_id)
                                ->first(array('firstName','lastName','cellphone','email','omang','passport','maritalstatus','gender','dob'));
                        $vehicle = MotorComprehensiveQuotes::where('quoteNumber', $policyInfo->quoteNumber)->first(array('make','model','estimatedValue as estimated_value','priorAccidents as claim_count','purpose','manufacturingYear as year','is_imported'));
                            if($vehicle == NULL)
                                $vehicle = Vehicle::where('policy_id',$policyInfo->id)->first(array('make','model','estimated_value','claim_count','purpose','year','is_imported'));


                            if(isset($vehicle->is_imported) && $vehicle->is_imported == 1){
                                $vehicle->is_imported = 'Yes';
                            }else{
                                $vehicle->is_imported = 'No';
                            }

                            $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($vehicle->is_imported);
                            $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make,$vehicle->is_imported,$vehicle->year);

                            $product = Product::where('id',$policyInfo->product_id)->first(array('id','name'));
                            $plan    = Productplan::where('product_id', $policyInfo->product_id)->first(array('name'));
                            // $premium = $this->getCalculatedPreminumForRenew($policyInfo->id);
                            $banking = CustomerBanking::where('policy_id',$policyInfo->id)->orderBy('id','desc')->first();
                            if (!isset($banking)) {
                                $banking = PaymentTransaction::where('policyNumber',$policyInfo->policyNumber)->orderBy('id','desc')->first();
                                $banking['billing'] = $banking->paymentMethod;
                            }
                            // if($premium->getData()->status == 200){
                            //     $premium = $premium->getData()->premium;
                            // }else{
                            //     $premium = [];
                            // }

                            $discSurData = PolicyDiscountSurcharge::where('policy_id',$policyInfo->id)->orderBy('id','desc')->first('new_value');
                            $premium     = MotorComprehensiveQuotes::where('quoteNumber',$policyInfo->quoteNumber)->orderBy('id','desc')->first();
                            if (isset($discSurData)) {

                                $annual_Premium    = null;
                                $monthly_Premium   = null;
                                $three_Installment = null;
                                $total_premium     = null;

                                if (isset($policyInfo->premium) && isset($policyInfo->premium_freq)) {
                                    $total_premium = $this->getMotorComprehensivePremium($policyInfo->policyNumber);
                                    $total_premium = round($total_premium['annual'],2);

                                    if ($policyInfo->premium_freq == 1) {
                                        $monthly_Premium = $policyInfo->premium;
                                    } else {
                                        $policyCon = new PolicyController();
                                        $premium = $policyCon->getMonthlyPrem(3,$total_premium);
                                        $monthly_Premium = round($premium,2);
                                    }

                                    if ($policyInfo->premium_freq == 2) {
                                        $three_Installment = $policyInfo->premium;
                                    } else {
                                        $premium = $total_premium / 3;
                                        $three_Installment = round($premium,2);
                                    }

                                    if ($policyInfo->premium_freq == 3) {
                                        $annual_Premium = $policyInfo->premium;
                                    } else {
                                        $annual_Premium = $total_premium;
                                    }
                                }

                                $premium = [
                                    'monthly'         => $monthly_Premium,
                                    'threeInstalment' => $three_Installment,
                                    'annual'          => $annual_Premium,
                                ];

                            } elseif (isset($premium)) {
                                $premium = [
                                    'monthly'         => $premium->premiumMonthly,
                                    'threeInstalment' => $premium->premium3Inst,
                                    'annual'          => $premium->premiumAnnually,
                                ];
                            } else {
                                $premium = [];
                            }

                            return response()->json([
                                'Status' => 'True',
                                'Description' => 'policy Information',
                                'information'=>[
                                    'customer'   => $customer,
                                    'vehicle'    => $vehicle,
                                    'product'    => $product,
                                    'plan'       => $plan,
                                    'policyInfo' => $policyInfo,
                                    'premium'    => $premium,
                                    'banking'    => $banking,
                                    'dataMake'   => $dataMake,
                                    'dataModel'  => $dataModel,
                                    'getInfo'    => $getInfo,
                                    'linkInfo'    => $linkInfo,
                                    'frequency'  => $frequency,
                                    'paymentUrlInfo' => $paymentUrlInfo
                                ]
                            ], 200);
                        }else{
                            return response()->json(['Status' => 'Failed', 'Description' => 'Policy information not found'], 401);
                        }
                    }else{
                        return response()->json(['Status' => 'Failed', 'Description' => 'Token information not found'], 401);
                    }
                }else{
                    return response()->json(['Status' => 'Failed', 'Description' => 'Invalid token'], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed', 'Description' => 'Token found empty'], 401);
            }
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed', 'Description' => $e->getMessage()], 401);
        }
    }

    public function getPolicyInformationReinstateArrears(Request $request){
        try{
            if($request->get('token')){
                $token = base64_decode($request->get('token'));
                if($token){
                    $getInfo = OneTimePaymentURL::where('token',$request->get('token'))->first();
                    $linkInfo = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->token)->first();
                    $frequency = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->token)->value('frequency');
                    $paymentUrlInfo = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/reinstate_fresh/'.$request->token)
                                    ->orWhere('url', 'https://pay.alphadirect.co.bw/reinstate_arrears/'.$request->token)->first();
                    if($getInfo){
                        $policyInfo = Policy::where('policyNumber',$getInfo->policyNumber)->first(array('policyNumber','id','customer_id','premium_freq','product_id','status','sum_assured','premium','quoteNumber'));
                        if($policyInfo){
                            $customer = Customer::join('customer_profile','customer_profile.customer_id','=','customer.id')
                                ->where('customer.id',$policyInfo->customer_id)
                                ->first(array('firstName','lastName','cellphone','email','omang','passport','maritalstatus','gender','dob'));
                            $vehicle = MotorComprehensiveQuotes::where('quoteNumber', $policyInfo->quoteNumber)->first(array('make','model','estimatedValue as estimated_value','priorAccidents as claim_count','purpose','manufacturingYear as year','is_imported'));
                            if($vehicle == NULL)
                                $vehicle = Vehicle::where('policy_id',$policyInfo->id)->first(array('make','model','estimated_value','claim_count','purpose','year','is_imported'));

                            if(isset($vehicle->is_imported) && $vehicle->is_imported == 1){
                                $vehicle->is_imported = 'Yes';
                            }else{
                                $vehicle->is_imported = 'No';
                            }

                            $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($vehicle->is_imported);
                            $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make,$vehicle->is_imported,$vehicle->year);

                            $product = Product::where('id',$policyInfo->product_id)->first(array('id','name'));
                            $plan    = Productplan::where('product_id', $policyInfo->product_id)->first(array('name'));
                            // $premium = $this->getCalculatedPreminumForRenew($policyInfo->id);
                            $banking = CustomerBanking::where('policy_id',$policyInfo->id)->orderBy('id','desc')->first();
                            if (!isset($banking)) {
                                $banking = PaymentTransaction::where('policyNumber',$policyInfo->policyNumber)->first();
                                $banking['billing'] = $banking->paymentMethod;
                            }

                            $balance = $this->getPolicyBalance($policyInfo->id);
                            if($balance->getStatusCode() == 200){
                                $balance = $balance->getData()->balance;
                                $balance = number_format(abs($balance), 2, '.', '');
                            }else{
                                $balance = null;
                            }

                            // dd($balance);
                            return response()->json([
                                'Status' => 'True',
                                'Description' => 'policy Information',
                                'information'=>[
                                    'customer'   => $customer,
                                    'vehicle'    => $vehicle,
                                    'product'    => $product,
                                    'plan'       => $plan,
                                    'policyInfo' => $policyInfo,
                                    // 'premium'    => $premium,
                                    'banking'    => $banking,
                                    'dataMake'   => $dataMake,
                                    'dataModel'  => $dataModel,
                                    'getInfo'    => $getInfo,
                                    'linkInfo'    => $linkInfo,
                                    'frequency'  => $frequency,
                                    'balance'    => $balance,
                                    'paymentUrlInfo' => $paymentUrlInfo
                                ]
                            ], 200);
                        }else{
                            return response()->json(['Status' => 'Failed', 'Description' => 'Policy information not found'], 401);
                        }
                    } else{
                        return response()->json(['Status' => 'Failed', 'Description' => 'Token information not found'], 401);
                    }
               }else{
                    return response()->json(['Status' => 'Failed', 'Description' => 'Invalid token'], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed', 'Description' => 'Token found empty'], 401);
            }
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed', 'Description' => $e->getMessage()], 401);
        }
    }

    public function getPolicyInformationRerate(Request $request){
        try{
            if($request->get('token')){
                $token = base64_decode($request->get('token'));
                if($token){
                    $getInfo = OneTimePaymentURL::where('token',$request->get('token'))->first();
                    $linkInfo = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->token)->first();
                    $frequency = PaymentUrls::where('url', 'https://pay.alphadirect.co.bw/rerate-premium-pay/'.$request->token)->value('frequency');
                    if($getInfo){
                        $policyInfo = Policy::where('policyNumber',$getInfo->policyNumber)->first(array('policyNumber','id','customer_id','premium_freq','product_id','status','sum_assured','premium','quoteNumber'));
                        if($policyInfo){
                            $customer = Customer::join('customer_profile','customer_profile.customer_id','=','customer.id')
                                ->where('customer.id',$policyInfo->customer_id)
                                ->first(array('firstName','lastName','cellphone','email','omang','passport','maritalstatus','gender','dob'));
                            $vehicle = MotorComprehensiveQuotes::where('quoteNumber', $policyInfo->quoteNumber)->first(array('make','model','estimatedValue as estimated_value','priorAccidents as claim_count','purpose','manufacturingYear as year','is_imported'));
                            if($vehicle == NULL)
                                $vehicle = Vehicle::where('policy_id',$policyInfo->id)->first(array('make','model','estimated_value','claim_count','purpose','year','is_imported'));


                            if(isset($vehicle->is_imported) && $vehicle->is_imported == 1){
                                $vehicle->is_imported = 'Yes';
                            }else{
                                $vehicle->is_imported = 'No';
                            }

                            $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($vehicle->is_imported);
                            $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make,$vehicle->is_imported,$vehicle->year);

                            $product = Product::where('id',$policyInfo->product_id)->first(array('id','name'));
                            $plan    = Productplan::where('product_id', $policyInfo->product_id)->first(array('name'));
                            // $premium = $this->getCalculatedPreminumForRenew($policyInfo->id);
                            $banking = CustomerBanking::where('policy_id',$policyInfo->id)->first();
                            if (!isset($banking)) {
                                $banking = PaymentTransaction::where('policyNumber',$policyInfo->policyNumber)->first();
                                $banking['billing'] = $banking->paymentMethod;
                            }
                            // if($premium->getData()->status == 200){
                            //     $premium = $premium->getData()->premium;
                            // }else{
                            //     $premium = [];
                            // }

                            $discSurData = PolicyDiscountSurcharge::where('policy_id',$policyInfo->id)->orderBy('id','desc')->first('new_value');
                            $premium     = MotorComprehensiveQuotes::where('quoteNumber',$policyInfo->quoteNumber)->orderBy('id','desc')->first();
                            if (isset($discSurData)) {

                                $annual_Premium    = null;
                                $monthly_Premium   = null;
                                $three_Installment = null;
                                $total_premium     = null;

                                if (isset($linkInfo->frequency)) {
                                    $policyCon       = new PolicyController();
                                    $total_premium   = $discSurData->new_value;
                                    $premium         = $policyCon->getMonthlyPrem(3,$total_premium);
                                    $monthly_Premium = round($premium,2);

                                    $premium           = $total_premium / 3;
                                    $three_Installment = round($premium,2);

                                    $annual_Premium = $total_premium;
                                }

                                $premium = [
                                    'monthly'         => $monthly_Premium,
                                    'threeInstalment' => $three_Installment,
                                    'annual'          => $annual_Premium,
                                ];

                            } elseif (isset($premium)) {
                                $premium = [
                                    'monthly'         => $premium->premiumMonthly,
                                    'threeInstalment' => $premium->premium3Inst,
                                    'annual'          => $premium->premiumAnnually,
                                ];
                            } else {
                                $premium = [];
                            }

                            return response()->json([
                                'Status' => 'True',
                                'Description' => 'policy Information',
                                'information'=>[
                                    'customer'   => $customer,
                                    'vehicle'    => $vehicle,
                                    'product'    => $product,
                                    'plan'       => $plan,
                                    'policyInfo' => $policyInfo,
                                    'premium'    => $premium,
                                    'banking'    => $banking,
                                    'dataMake'   => $dataMake,
                                    'dataModel'  => $dataModel,
                                    'getInfo'    => $getInfo,
                                    'linkInfo'    => $linkInfo,
                                    'frequency'  => $frequency
                                ]
                            ], 200);
                        }else{
                            return response()->json(['Status' => 'Failed', 'Description' => 'Policy information not found'], 401);
                        }
                    }else{
                        return response()->json(['Status' => 'Failed', 'Description' => 'Token information not found'], 401);
                    }
                }else{
                    return response()->json(['Status' => 'Failed', 'Description' => 'Invalid token'], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed', 'Description' => 'Token found empty'], 401);
            }
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed', 'Description' => $e->getMessage()], 401);
        }
    }



    public function getPolicyInformationRenew(Request $request){
        try{
            if($request->get('token')){
                $token = base64_decode($request->get('token'));
                if($token){
                    $getInfo = OneTimePaymentURL::where('token',$request->get('token'))->first(['policyNumber','status']);
                    if($getInfo){
                        $policyInfo = Policy::where('policyNumber',$getInfo->policyNumber)->first(array('policyNumber','id','customer_id','premium_freq','product_id','status','sum_assured','premium','quoteNumber'));
                        if($policyInfo){
                            $customer = Customer::join('customer_profile','customer_profile.customer_id','=','customer.id')
                                ->where('customer.id',$policyInfo->customer_id)
                                ->first(array('firstName','lastName','cellphone','email','omang','passport','maritalstatus','gender','dob'));
                            // $vehicle = MotorComprehensiveQuotes::where('quoteNumber', $policyInfo->quoteNumber)->first(array('make','model','estimatedValue as estimated_value','priorAccidents as claim_count','purpose','manufacturingYear as year','is_imported'));
                            // if($vehicle == NULL)
                            $vehicle = Vehicle::where('policy_id',$policyInfo->id)->first(array('make','model','estimated_value','claim_count','purpose','year','is_imported'));

                            if($vehicle->is_imported == 1){
                                $vehicle->is_imported = 'Yes';
                            }else{
                                $vehicle->is_imported = 'No';
                            }

                            $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($vehicle->is_imported);
                            $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($vehicle->make,$vehicle->is_imported,$vehicle->year);

                            $product = Product::where('id',$policyInfo->product_id)->first(array('id','name'));
                            $plan = Productplan::where('product_id', $policyInfo->product_id)->first(array('name'));
                            $premium = $this->getCalculatedPreminumForRenew($policyInfo->id);
                            $banking = CustomerBanking::where('policy_id',$policyInfo->id)->first();
                            if($premium->getData()->status == 200){
                                $premium = $premium->getData()->premium;
                            }else{
                                $premium = [];
                            }

                            return response()->json([
                                'Status' => 'True',
                                'Description' => 'policy Information',
                                'information'=>[
                                    'customer'=>$customer,
                                    'vehicle'=>$vehicle,
                                    'product'=>$product,
                                    'plan'=>$plan,
                                    'policyInfo'=>$policyInfo,
                                    'premium'=>$premium,
                                    'banking'=>$banking,
                                    'dataMake'=>$dataMake,
                                    'dataModel'=>$dataModel,
                                    'getInfo'=>$getInfo,
                                ]
                            ], 200);
                        }else{
                            return response()->json(['Status' => 'Failed', 'Description' => 'Policy information not found'], 401);
                        }
                    }else{
                        return response()->json(['Status' => 'Failed', 'Description' => 'Token information not found'], 401);
                    }
                }else{
                    return response()->json(['Status' => 'Failed', 'Description' => 'Invalid token'], 401);
                }
            }else{
                return response()->json(['Status' => 'Failed', 'Description' => 'Token found empty'], 401);
            }
        }catch(\Exception $e){
            return response()->json(['Status' => 'Failed', 'Description' => $e->getMessage()], 401);
        }
    }


    public function getCalculatedPreminumForRenew($policyId)
    {
        try {
            $policy      = Policy::where('id', $policyId)->first();
            $data        = PolicyDiscountSurcharge::where('policy_id',$policyId)->orderBy('id','desc')->first('new_value');
            $renewal     = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
            $new_premium = null;
            if (isset($data)) {
                $new_premium = $data->new_value;
            } elseif (isset($renewal) && isset($renewal->new_premium)) {
                $new_premium = $renewal->new_premium;
            } else {
                $new_premium = $policy->premium;
            }
            // $new_premium = NULL;
            // if (isset($data)) {
            //     $new_premium = $data->new_value;
            // } else {
            //     $policy = Policy::where('id', $policyId)->first(array('quoteNumber'));
            //     $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->orderBy('id','desc')->first(array('premiumAnnually'));
            //     $new_premium = $quote->premiumAnnually;
            // }

            // if (isset($data)) {
                $policy = new PolicyController();
                $monthlyPremium = $policy->getMonthlyPrem(3,$new_premium);
                $monthlyPremium = round($monthlyPremium,2);


                $threeInstalments = $new_premium / 3;
                $threeInstalments = round($threeInstalments,2);

                $annualPremium = $new_premium;

                $premium = [
                    'monthly'         => $monthlyPremium,
                    'threeInstalment' => $threeInstalments,
                    'annual'          => $annualPremium,
                ];

                return response()->json(['success'=>'true','message' => 'Transaction successfull','premium'=>$premium,'status'=>'200']);

            // } else {
            //     return response()->json(['success'=>'false','message' => 'Data not found','status' => '401']);
            // }
        } catch (Exception $ex) {
            return response()->json(['success'=>'false','message' => $ex->getMessage(),'status' => '401']);
        }
    }

    public function calculatePremiumForRenewal($freq,$premiumAmount)
    {
        try {
            if(isset($freq) && isset($premiumAmount)){

                if($freq == 2){
                    $premium = $premiumAmount / 3;
                    $premium = round($premium,2);

                    $first_premium = $premium;

                    return response()->json(['status'=>200,'message' => 'Calculated premium successfully', 'premium' => $premium, 'first_premium' => $first_premium],200);

                }
                elseif($freq == 3){
                    $premium = $premiumAmount;
                    $first_premium = $premium;

                    return response()->json(['status'=>200,'message' => 'Calculated premium successfully', 'premium' => $premium, 'first_premium' => $first_premium],200);

                }
                elseif($freq == 1){
                    $policyCon = new PolicyController();
                    $premium = $policyCon->getMonthlyPrem(3,$premiumAmount);
                    $premium = round($premium,2);
                    $first_premium = $premium;

                    return response()->json(['status'=>200,'message' => 'Calculated premium successfully', 'premium' => $premium, 'first_premium' => $first_premium],200);

                }
                else{
                    return response()->json(['status'=>401,'message' => 'Frequency or Premium not found'],401);
                }

            } else{
                return response()->json(['status'=>401,'message' => 'Frequency or Premium not found'],401);
            }
        }catch(\Exception $ex){
            return response()->json(['status'=>401,'message' => $ex->getMessage()],401);
        }
    }


    public function logPaymentRenew(Request $request){
       try{
            // $renew_data = $request->all();

            $policy = Policy::where('id',$request->get('policy_id'))->first();
            if(isset($request->email)){
                $customer = Customer::where('id',$policy->customer_id)->first();
                if (isset($customer)) {
                    $customer->email = $request->email;
                    $customer->save();
                }
            }
            $renewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
            $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
            $renew_amount = null;
            if (isset($data)) {
                $renew_amount = $data->new_value;
            } elseif (isset($renewal) && isset($renewal->new_premium)) {
                $renew_amount = $renewal->new_premium;
            } else {
                $renew_amount = $policy->premium;
            }

            if($policy){
                $banking = CustomerBanking::where('policy_id',$policy->id)->first();
                $cancel = $this->cancelPolicyPayment($policy->id,$banking->billing);

                if($banking == null)
                    $banking = new CustomerBanking();

                $banking->policy_id = $policy->id;
                $banking->billing = $request->payment_method;
                $banking->bankName = $request->BankName;
                $banking->branchCode = $request->BranchCode;
                $banking->accountType = $request->accountType;
                $banking->accountNumber = $request->accountNumber;
                $banking->billingStartDate = Carbon::now()->format('Y-m-d');
                $banking->billing_day = Carbon::now()->format('d');;
                $banking->save();

                // $policy->premium_freq = $request->frequency;
                // $policy->premium = $request->premium;
                // $policy->save();

                $newPaymentMethod = $request->get('payment_method');

                if($newPaymentMethod == 'RealPay'){
                    $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    // $addLog = $log->logEvent($policy->id, 1);
                    // $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
                    // $stringArr = \Opis\Closure\serialize($responseArr);

                    $transaction = new Transaction();
                    $transaction->policyNumber = $policy->policyNumber;
                    $transaction->amount = $policy->premium;
                    $transaction->customer_id = $policy->customer_id;
                    $transaction->realPayTransaction_id = $policy->id;
                    $transaction->referenceNumber = $policy->policyNumber;
                    $transaction->status = "PENDING";
                    $transaction->save();

                    // if ($addLog == true) {
                        // $payRequest = new RealpayPaymentRequest();
                        // $payRequest->policy_id = $policy->id;
                        // $payRequest->first_premium = null;
                        // $payRequest->premium = $policy->premium;
                        // $payRequest->billing_day = $banking->billing_day;
                        // $payRequest->billing_date = $banking->billingStartDate;
                        // $payRequest->first_premium_contract = null;
                        // $payRequest->contract = null;
                        // $payRequest->status = 0;
                        // $payRequest->response = $stringArr;
                        // $payRequest->frequency = $policy->premium_freq;
                        // $payRequest->clientCreated = 0;
                        // $payRequest->contractCreated = 0;
                        // $payRequest->save();
                        $realpay = $log->logRealpayPaymentForPolicyRenewal($request);
                        // dd($realpay);
                        if ($realpay->getData()->status == 200) {
                            $request->premium = $realpay->getData()->premium;
                            $request->first_premium = $realpay->getData()->first_premium;
                            if (isset($realpay->getData()->first_premium)) {
                                $request->term_permium = $realpay->getData()->first_premium;
                            }

                            $policy_renew = $this->policyRenewPay($request);
                            event(new \AlphaDirect\Events\policyLifecycle($policy->id , "Renewed"));
                            // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                            return response()->json(['success'=>'true','message' => 'Realpay payment logged successfully'],200);

                        } else {
                            // $renew_data['premium'] = NULL;
                            // $renew_data['first_premium'] = NULL;

                            return response()->json(['success'=>'false','message' => 'Failed to logged Realpay payment.'.$realpay->getData()->message],200);
                        }
                    // }

                }elseif($newPaymentMethod == "cash"){

                    // $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
                    // if (isset($data)) {
                    //     $renew_data['premium'] = $data->new_value;
                    // } else {
                    //     $renew_data['premium'] = $policy->premium;
                    // }

                    // $renew_data['term_permium'] = $renew_data['premium'];

                    // if ($renew_data['frequency'] == 1) {
                    //     $renew_data['first_premium'] = $renew_data['premium'];
                    // } else {
                    //     $renew_data['first_premium'] = NULL;
                    // }

                    // $policy_renew = $this->policyRenewPay($renew_data);
                    // event(new \AlphaDirect\Events\policyLifecycle($policy->id , "Renewed"));
                    // return response()->json(['success'=>'true','message' => 'Cash payment logged successfully'],200);
                } elseif ($newPaymentMethod == "DPO") {
                    $policyCon = new PolicyController();
                    $renew_premium = $policyCon->calculatePremiumForRenewal($request->frequency,$renew_amount);

                    if ($renew_premium->getData()->status == 200) {
                        $request->premium = $renew_amount;//renew_premium->getData()->premium;
                        $request->cal_premium = $renew_premium->getData()->premium;
                        $request->first_premium = $renew_premium->getData()->first_premium;
                        $request->term_permium = $renew_premium->getData()->first_premium;

                        // dd($renew_amount,$request->premium);
                        $dpo                  = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource  = 'pay.alphadirect.co.bw';
                        $request->amount      = $request->cal_premium;
                        $request->requestType = 'renew';
                        $dpoReturn            = $dpo->findPolicyForOnlinePayment($request);
                        // dd($dpoReturn);
                        return $dpoReturn;
                    } else{
                        return response()->json(['success'=>'false','message' => 'Something went wrong. Premium not found'],401);
                    }
                }
                else{
                    return response()->json(['success'=>'true','message' => 'Payment method not found'],401);
                }

            }else{
                return response()->json(['success'=>'false','message' => 'Policy data not found'],401);
            }

       }catch(\Exception $ex){
           return response()->json(['success'=>'false','message' => $ex->getMessage()],401);
       }
    }

    public function policyRenewPay(Request $request)
    {
        // dd($request->all());
        try {
            $policyDetails = Policy::where('id', $request->policy_id)->first();
            // dd($policyDetails);

            if ($policyDetails == null) {
                return response()->json(['status' => '401','message' => 'Policy not available for edit.'], 401);
            }

            $policyRenewal = PolicyRenewal::where('policyNumber', $policyDetails->policyNumber)->orderBy('id', 'desc')->first();

            if (isset($policyRenewal->expiry_date) && Carbon::parse($policyRenewal->expiry_date)->lt(Carbon::now())) {
                $start_date  = Carbon::now()->format('Y-m-d');
                $expiry_date = Carbon::now()->addYear()->subDays(1)->format('Y-m-d');

            } else {
                $start_date  = Carbon::createFromFormat('Y-m-d', $policyRenewal->expiry_date)->addDays(1)->format('Y-m-d');
                $expiry_date = Carbon::createFromFormat('Y-m-d', $start_date)->addYear()->subDays(1)->format('Y-m-d');
            }

            $transaction = Transaction::where('policyNumber',$policyDetails->policyNumber)->orderBy('id','desc')->first();
            $status      = 'Deactive';

            if (isset($policyRenewal->expiry_date)) {
                // if ($request->payment_method == 'Cash') {
                    if ($policyRenewal->expiry_date < Carbon::now()) {
                        $status = 'Deactive';
                    } else {
                        $status = 'Active';
                    }
                // } elseif ($request->payment_method == 'Realpay') {
                    // if ($policyRenewal->expiry_date < Carbon::now()) {
                    //     $status = 'Deactive';
                    // } else {
                    //     $status = 'Active';
                    // }
                // }
            }

            // $premium_value = NULL;
            // $first_premium = NULL;

            // $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
            // if (isset($data)) {
            //     $request['premium'] = $data->new_value;
            // } else {
            //     $request['premium'] = $policy->premium;
            // }

            // if ($request->frequency == 1) {
            //     $premium_value = $request->premium;
            //     $first_premium = $request->first_premium;
            // } elseif ($request->frequency == 2) {
            //     $premium_value = $request->premium;
            // } elseif ($request->frequency == 3) {
            //     $premium_value = $request->premium;
            // }

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $oldTermPaymentMethod = $realpay->checkPaymentMethod($request->policy_id);

        //policy term
        // policy old term data

        // $total_terms = PolicyTerm::where('policy_id',$renew_data['policy_id'])->get();
        $term_count = PolicyTerm::where('policy_id',$request->policy_id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->count();

        $term_id = NULL;

        if ($term_count == 0) {
            $data = [
                'policy_id' => $request->policy_id,
                'term_start_date' => $policyDetails->policyActivatedDate,
                'term_end_date' => $policyRenewal->expiry_date,
                // 'premium' => $policyDetails->premium,
                'premium' => $policyDetails->first_premium,
                'annual_premium' => $policyRenewal->old_premium,
                'renewed_by' => NULL,
                'renewals_date' => $policyRenewal->expiry_date,
                'frequency' => $policyDetails->premium_freq,
                'first_premium' => $policyDetails->first_premium,
                'billing_start_date' => $policyDetails->billingStartDate,
                'policy_documents' => NULL,
                'policyActivatedDate' => $policyDetails->policyActivatedDate,
                'payment_method' => $oldTermPaymentMethod,
                'payment_reference' => $request->policy_id,
                'trans_type' => 'NEW BUSINESS',
                'status' => $status,
                'created_at' => Carbon::now()->format('Y-m-d'),

            ];
            $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
        }

        // policy new term data

        $new_status = 'Deactive';

        // if ($request->payment_method == 'Cash') {
            if ($status == 'Deactive') {
                if ($expiry_date < Carbon::now()) {
                    $new_status = 'Deactive';
                } else {
                    $new_status = 'Active';
                }
            }
        // } elseif ($request->payment_method == 'Realpay') {
        //     if ($status == 'Deactive') {
        //         if ($expiry_date < Carbon::now()) {
        //             $new_status = 'Deactive';
        //         } else {
        //             $new_status = 'Active';
        //         }
        //     }
        // }

        $term_data = PolicyTerm::where('policy_id',$request->policy_id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->orderBy('id','desc')->first(array('id','term_end_date'));

        // new term data
        $newData = [
            'policy_id' => $request->policy_id,
            'term_start_date' => $start_date,
            'term_end_date' => $expiry_date,
            'premium' => ($request->term_permium) ? $request->term_permium : $request->first_premium,
            'annual_premium' => $request->premium,
            'renewed_by' => NULL,
            'renewals_date' =>  Carbon::parse($expiry_date)->addDays(1)->format('Y-m-d'),
            'frequency' => $request->frequency,
            'first_premium' => ($request->term_permium) ? $request->term_permium : $request->first_premium,
            'billing_start_date' =>  Carbon::now()->format('Y-m-d'),
            'policy_documents' => NULL,
            'policyActivatedDate' => $start_date,
            'payment_method' => $request->payment_method,
            'payment_reference' => $request->policy_id,
            'trans_type' => 'RENEW',
            'status' => $new_status,
            'created_at' => Carbon::now()->format('Y-m-d'),

        ];
        $new_term_id = PolicyTerm::addPolicyTerm($newData);

        if (isset($new_term_id)) {
            if ($term_data->term_end_date < Carbon::now()) {
                $policyDetails->term_id = $new_term_id;
                $policyDetails->premium = ($request->term_permium) ? $request->term_permium : $request->first_premium;
                $policyDetails->first_premium = $request->first_premium;
                $policyDetails->premium_freq = $request->frequency;
                $policyDetails->policyActivatedDate = $start_date;
                $policyDetails->billingStartDate =  Carbon::now()->format('Y-m-d');
                $policyDetails->term_start_date = $start_date;
                $policyDetails->term_end_date = $expiry_date;
                if (isset($policyRenewal)) {
                    $policyDetails->expiry_date = $policyRenewal->expiry_date;
                    $policyDetails->sum_assured = $policyRenewal->sum_assured;
                } else {
                    $policyDetails->expiry_date = NULL;
                    $policyDetails->sum_assured = NULL;
                }

                // $renew_data['term_id'] = $term_id;

                // $term_id = null;
                if ($term_count == 0) {
                    $term_id = $newBusiness_term_id;
                } else {
                    $term_id = $term_data->id;
                }


                $oldVehicleData = $this->getOldVehicleImages($policyDetails->id,$term_id);
                if ($oldVehicleData == true) {
                    $vehicle                      = Vehicle::where('policy_id',$policyDetails->id)->first();
                    $vehicle->front               = NULL;
                    $vehicle->back                = NULL;
                    $vehicle->left                = NULL;
                    $vehicle->right               = NULL;
                    $vehicle->vehicleRegistration = NULL;
                    $vehicle->vehicle_valuation   = NULL;
                    $vehicle->save();
                }

                $document = new DocumentController();
                $generatePolicyDocument = $document->generatePolicyDocument($policyDetails->id);

                if ($generatePolicyDocument == true) {
                    $sentBy = 'System';
                    $getDocument =  $document->sendPolicyDocumentForRenew($policyDetails->id,$sentBy);
                }

            } else {
                if (isset($term_id)) {
                    $policyDetails->term_id = $term_id;
                }
            }

            $policyDetails->save();


            if ($request->payment_method == 'DPO') {

                $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policyDetails->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policyDetails->customer_id,
                ];


                //fire event send sms and email when policy is created
                $event                                       = VerifyTokenEvent::dispatch($data);
                // dd($event);
                $event                                       = $event[0];
                $paymentTransaction                          = new PaymentTransaction();
                $paymentTransaction->policyNumber            = $policyDetails->policyNumber;
                $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
                $paymentTransaction->amount                  = isset($event['amount']) ? $event['amount'] : 0.00;
                $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
                $paymentTransaction->paymentDate             = Carbon::now();
                $paymentTransaction->paymentMethod           = 'DPO';
                $paymentTransaction->numberOfInstalmentsPaid = 0;
                $paymentTransaction->paymentFrequency        = isset($request->payment_frequency) ?  $request->payment_frequency : 1;
                $paymentTransaction->TransID                 = isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null;
                $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
                $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
                $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
                $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
                $paymentTransaction->save();
                $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
                if( $event['status'] == 1 )
                {
                    $action                = $policyController->action($policyDetails->id, 1, 'DPO');  //set the Policy status to active
                    $policyRenewalArr = [
                        'id'               => $policyRenewal->id,
                        'policyNumber'     => $policyRenewal->policyNumber,
                        'product_id'       => $policyRenewal->product_id,
                        'premium_freq'     => $policyRenewal->premium_freq,
                        'latestSchedule'   => ScheduleTransaction::where('policy_id', $policyDetails->policy_id)->orderBy('installment', 'desc')->value('installment'),
                        'premium'          => $paymentTransaction->amount,
                        'customer_id'      => $policyRenewal->customer_id,
                        'email'            => Customer::where('id', $policyDetails->customer_id)->value('email'),
                        'billingStartDate' => $policyRenewal->billingStartDate
                    ];

                    RenewPolicySchedulesEvent::dispatch($policyRenewalArr);
                }
            }

            $policyRenewal->is_renewed = 1;
            $policyRenewal->save();


            $payment_link = OneTimePaymentURL::where('policyNumber',$policyDetails->policyNumber)->orderBy('id','desc')->first();
            // dd($payment_link);
            if(isset($payment_link)){
                $payment_link->status = 1;
                $payment_link->save();
            }

        }

        if ($request->payment_method == 'DPO' && $request->leadSource = 'pay.alphadirect.co.bw') {

            if ($policyDetails->save() && isset($new_term_id) && $paymentTransaction->status == 'SUCCESS') {
                event(new \AlphaDirect\Events\policyLifecycle($policyDetails->id , "Renewed"));
                return redirect(env('TestPay_URL').'thankyou_renew'); //PAY_URL
                // return response()->json(['status'=>'200','message' => 'DPO payment logged successfully'],200);
            } else {
                return redirect(env('TestPay_URL').'renew_error');
                // return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
            }

        } elseif ( $request->payment_method == 'Realpay' && $request->leadSource = 'pay.alphadirect.co.bw') {
            if ($policyDetails->save() && isset($new_term_id)) {
                return response()->json(['status' => 200, 'message' => 'Policy renewed succesfully.', 'policy_number' => $policyDetails->policyNumber ], 200);
            } else {
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
                return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
            }
        } else{
            if ($policyDetails->save() && isset($new_term_id)) {
                return response()->json(['status' => 200, 'message' => 'Policy renewed succesfully.', 'policy_number' => $policyDetails->policyNumber ], 200);
            } else {
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
                return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
            }
        }


        }catch(Exception $e){
            return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
        }
    }

    public function policyRenewalPaymentFailed(Request $request)
    {
        /* $request->validate([
            'policyNumber' => 'required',
            'payment_method' => 'required'
        ]); */
        $policy_number = base64_decode($request->policy_number);
        $amount        = base64_decode($request->amount);
        /* print_r($policy_number. '<br>');
        print_r($amount);
        dd($request->all()); */
        DB::beginTransaction();
        try{
            $policy = Policy::where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                ];


            //fire event send sms and email when policy is created
            $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = (float)str_replace(',','',$amount) ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->frequency) ?  $request->frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->save();

            DB::commit();

                $notification = array(
                    'message'      => 'Payment failed, please try again later',
                    'amount'       => $amount,
                    'alert-type'   => 'failed',
                    'policyNumber' => $policy_number,
                    'amount'       => $amount
                );

                $sms = new PaymentTransaction();
                $sent = $sms->sendSMSOnFailedPayments($policy_number,$amount);
                return redirect(env('START_URL').'payment_failed.php')->with('notification', $notification);


        }catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function policyRenewalPaymentDeclined(Request $request)
    {
        /* $request->validate([
            'policyNumber' => 'required',
            'payment_method' => 'required'
        ]); */
        $policy_number = base64_decode($request->policy_number);
        $amount        = base64_decode($request->amount);
        /* print_r($policy_number. '<br>');
        print_r($amount);
        dd($request->all()); */
        DB::beginTransaction();
        try{
            $policy = Policy::where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                ];


            //fire event send sms and email when policy is created
            /* $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = (float)str_replace(',','',$amount) ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->frequency) ?  $request->frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->save(); */

            DB::commit();

                return response()->json([
                    'message'      => 'Payment is canceled.',
                    'alert-type'   => 'failed',
                    'amount'       => $amount,
                    'policyNumber' => $policy_number,
                    'amount'       => $amount
                ]);

        }catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function cancelPolicyPayment($policyId,$paymentMethod){
        try{
            $policy = Policy::where('id',$policyId)->orderBy('id','desc')->first();
            if ($paymentMethod == 'VCS') {
                $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();
                if($transctionsRow && $transctionsRow->referenceNumber){
                    $referenceNumber = $transctionsRow->referenceNumber;
                    $vcs = new PaymentController;
                    $vcs->suspendTransactionOnVCS($referenceNumber);
                }
                return response()->json(['success' => true, 'Message' => 'successful cancellation on VCS'], 200);
            } elseif ($paymentMethod == 'Realpay') {
                $check = RealpayClientContracts::where('policy_id',$policy->id)
                    ->orderBy('id','desc')
                    ->first();
                if($check == null){
                    $check = RealpayPaymentRequest::where('policy_id', $policy->id)->first();
                    $contractNumber = $check->contractNumber;
                }else{
                    $contractNumber = $check->contract_number;
                }
                if ($check != null && $check->status == 1) {
                    $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $addLog = $log->logEvent($policy->id, 2);
                    if ($addLog) {
                        $request                           = new RealpayCancelRequests();
                        $request->policy_id                = $policy->id;
                        $request->leftout_premium_contract = null;
                        $request->contract                 = $contractNumber;
                        $request->cancel_status            = 0;
                        $request->save();
                        return response()->json(['success' => true, 'Message' => 'successful cancellation on Realpay'], 200);
                    } else {
                        return response()->json(['success' => true, 'Message' => 'successful cancellation on Realpay'], 200);
                    }
                } else {
                    return response()->json(['success' => true, 'Message' => 'Banking details not found'], 200);
                }
            } else {
                $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();
                if($transctionsRow && $transctionsRow->referenceNumber){
                    $referenceNumber = $transctionsRow->referenceNumber;
                    $vcs = new PaymentController;
                    $vcs->suspendTransactionOnVCS($referenceNumber);
                }
                return response()->json(['success' => true, 'Message' => 'successful'], 200);
            }
        }catch(\Exception $ex){
            return response()->json(['success'=>false,'message' => $ex->getMessage()],401);
        }
    }

    /*
     * Pass data through ajax call
     * for recievable tab under policy edit
     * param: policy id ($id)
     * @return mixed
     */
    public function recievableData($id)
    {
        $ledger_graphite = Ledger::with('policy.customer')->where('policy_id', $id)->whereNotNull('credit')->get();
        $ledger_archive = ModelsLedgerArchive::with('policy.customer')->where('policy_id', $id)->whereNotNull('credit')->get();
        $merged = $ledger_archive->merge($ledger_graphite);
        $ledger = $merged->all();

        return DataTables::of($ledger)
            ->editColumn('balance', function ($ledger) {
                if ($ledger->balance == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    // if ($ledger->balance < 0) {
                    //     $cred = '<span class="kt-font-bold kt-font-primary">- P' . number_format(abs($ledger->balance), 2, '.', '') . '</span>';
                    // } else {
                        $cred = '<span class="kt-font-bold kt-font-primary">P' . number_format(abs($ledger->balance), 2, '.', '') . '</span>';
                    //}
                    return $cred;
                }
            })
            ->editColumn('debit', function ($ledger) {
                if ($ledger->debit == 0) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-success">P' . $ledger->debit . '</span>';
                    return $cred;
                }
            })
            ->rawColumns(['amount', 'status', 'trans_ref', 'actions', 'credit', 'customer_id', 'pmts_adjust', 'debit', 'unallocated', 'balance', 'other_charges', 'due_amount'])
            ->make(true);
    }

    public function getInvoice($id)
    {
        try {
            $ledger = Ledger::where('id', $id)->first(array('id', 'policy_id', 'customer_id', 'invoice_no'));
            if ($ledger != null) {
                $exists = Storage::disk('s3')->exists('MIS/' . $ledger->policy_id . '/' . 'Customer/' . $ledger->customer_id . '/' . 'Invoice/' . $ledger->invoice_no . '.pdf');
//                if ($exists == true) {
//                    return Redirect::to(Helper::getCloudFrontURL('MIS/' . $ledger->policy_id . '/' . 'Customer/' . $ledger->customer_id . '/' . 'Invoice/' . $ledger->invoice_no . '.pdf'));
//                } else {
                $ids = array();
                array_push($ids, $id);
                $path = Helper::generateInvoice($ids);
                return Redirect::to(Helper::getCloudFrontURL($path));
//                }
            }
        } catch (Exception $e) {
        }
    }

    /*
     * Pass data through ajax call
     * for invoice tab under policy edit
     * param: policy id ($id)
     * @return mixed
     */
    public function invoicingData($id)
    {
        $ledger_graphite = Ledger::with('policy.customer')->where('policy_id', $id)->whereNotNull('invoice_file')->whereNotNull('invoice_no')->get();
        $ledger_archive = ModelsLedgerArchive::with('policy.customer')->where('policy_id', $id)->whereNotNull('invoice_file')->whereNotNull('invoice_no')->get();
        $merged = $ledger_archive->merge($ledger_graphite);
        $ledger = $merged->all();

        return DataTables::of($ledger)
            ->editColumn('invoice_no', function ($ledger) {
                if ($ledger->invoice_no != null) {
                    $cred = '<a href="' . route('admin.policy.getInvoice', $ledger->id) . '" target="_blank"> ' . $ledger->invoice_no . '</a>';
                    return $cred;
                } else {

                    return '-';
                }
            })
            ->editColumn('balance', function ($ledger) {
                if ($ledger->balance == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    // if ($ledger->balance < 0) {
                    //     $cred = '<span class="kt-font-bold kt-font-primary">- P' . number_format(abs($ledger->balance), 2, '.', '') . '</span>';
                    // } else {
                        $cred = '<span class="kt-font-bold kt-font-primary">P' . number_format(abs($ledger->balance), 2, '.', '') . '</span>';
                    //}

                    return $cred;
                }
            })
            ->editColumn('trans_type', function ($ledger) {
                $trans_type = Transactiontype::where('id', $ledger->trans_type)->first();
                if ($trans_type != null) {
                    return $trans_type->name;
                } else {
                    return null;
                }
            })
            ->editColumn('trans_ref', function ($ledger) {
                if ($ledger->policyNumber != null) {
                    return $ledger->policyNumber;
                } else {
                    $trans_ref = Policy::where('id', $ledger->policy_id)->first();
                    return $trans_ref->policyNumber;
                }
            })
            ->editColumn('credit', function ($ledger) {
                if ($ledger->credit == 0) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-success">P' . $ledger->invoice_amount . '</span>';
                    return $cred;
                }
            })
            ->editColumn('debit', function ($ledger) {
                if ($ledger->debit == 0) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-danger">P' . $ledger->invoice_amount . '</span>';
                    return $cred;
                }
            })
            // ->editColumn('actions', function ($ledger) {
            //     $actions = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($ledger->invoice_file) . '" target="_blank" download class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Download Invoice">
            //                     <i class="la la-download"></i>
            //                 </a>';
            //     return $actions;
            // })
            ->editColumn('premium', function ($ledger) {
                if ($ledger->premium == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-primary">P' . $ledger->premium . '</span>';
                    return $cred;
                }
            })
            ->editColumn('other_charges', function ($ledger) {
                if ($ledger->other_charges == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-danger">P' . $ledger->other_charges . '</span>';
                    return $cred;
                }
            })
            ->editColumn('invoice_amount', function ($ledger) {
                if ($ledger->invoice_amount == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-primary">P' . $ledger->invoice_amount . '</span>';
                    return $cred;
                }
            })
            ->editColumn('due_amount', function ($ledger) {
                if ($ledger->due_amount == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-danger">P' . $ledger->due_amount . '</span>';
                    return $cred;
                }
            })
            ->editColumn('pmts_adjust', function ($ledger) {
                if ($ledger->pmts_adjust == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-primary">P' . $ledger->pmts_adjust . '</span>';
                    return $cred;
                }
            })
            ->editColumn('actions', function ($ledger) {
                // if($ledger->status=='Paid'){


                // $actions = '<a href="'. url('admin/policy/creditNote/'.$ledger->id) .'" target="_blank"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Creditnote">
                //                 CR
                //             </a>';
                $actions = '<a href="'. url('admin/policy/creditNoteView/'.$ledger->id) .'" target="_blank"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Creditnote">
                CR
               </a>';
                if($ledger->status != 'Reversed')
                {
                    $actions .= '<a href="" value="'.$ledger->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md ledger-invoice-confirm-delete" title="Delete">
                    <i class="la la-trash"></i>
                   </a>';
                }

                // }else{
                //     return $actions='';
                // }
                return $actions;
            })
            ->rawColumns(['invoice_no', 'amount', 'status', 'trans_ref', 'actions', 'credit', 'trans_sub_type', 'customer_id', 'trans_type', 'pmts_adjust', 'debit', 'unallocated', 'premium', 'balance', 'other_charges', 'invoice_amount', 'due_amount'])
            ->make(true);
    }

    /** Credit Note ****/
    // CreditNote by sanket sir
    // public function getCreditNote($ledger_id)
    // {
    //     $invoice = Ledger::where('id', $ledger_id)->first(array('trans_type', 'invoice_no', 'invoice_date', 'invoice_amount', 'due_date', 'debit', 'customer_id','amount_type', 'policy_id'));
    //     $vat = Ledger::where('id', '<',$ledger_id)->where('trans_type', 'Invoice VAT')->orderBy('id', 'desc')->first(array('debit'));
    //     $policy = Policy::where('id', $invoice->policy_id)->first(array('policyNumber'));
    //     $array = [
    //         'invoice'         => $invoice,
    //         'customer'        => Customer::where('id', $invoice->customer_id)->first(array('firstName', 'lastName')),
    //         'customerProfile' => CustomerProfile::where('customer_id', $invoice->customer_id)->first(array('address')),
    //         'vat'             => $vat->debit,
    //         'policyNumber'    => $policy->policyNumber
    //     ];
    //    $pdf = PDF::loadView('admin.notes.creditnote',$array);
    //     return $pdf->stream('creditnote.pdf');
    //     // return $pdf->download('creditnote.pdf');

    //     //return view('admin.policy.creditnote');
    // }

    public function creditNoteView($id)
    {
        $ledger = Ledger::where('id', $id)->whereNotNull('invoice_file')->whereNotNull('invoice_no')->first(array('id','policy_id','invoice_date'));

        if($ledger && $ledger->policy_id != null){
            $policy = Policy::where('id', $ledger->policy_id)->first(array('id','policyNumber','policyActivatedDate','premium','premium_freq'));
        }else{
            $policy = null;
        }

        $credit_note = CreditNote::where('invoice_id',$id)->first(array('status','no_of_days','transaction_effective_date','transaction_end_date','earned_premium','unearned_premium','credit_note_file'));

        if(isset($policy->policyNumber)){
            $policyactivatecancelleddates = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first('cancelled_date');
        }else{
            $policyactivatecancelleddates = null;
        }

        if ($policyactivatecancelleddates && $policyactivatecancelleddates->cancelled_date != null){
            $policy_cancelled_date = Carbon::parse($policyactivatecancelleddates->cancelled_date);
        }else{
            $policy_cancelled_date = null;
        }

        if ($policy && $policy->policyActivatedDate != null){
            $policy_activated_date = Carbon::parse($policy->policyActivatedDate);
        }else{
           $policy_activated_date = null;
        }

        if($policy_cancelled_date && $policy_activated_date != null){
            $no_of_active_days = $policy_cancelled_date->diffInDays($policy_activated_date);
        }else{
            $no_of_active_days = null;
        }
        $earned_premium = null;
        $unearned_premium = null;
        $one_day_premium = null;
            if($policy && $policy->premium != null){
                //dd($policy->premium);
                if($policy->premium_freq == 3){
                    $one_day_premium = $policy->premium / 365.25;
                }else{
                    $one_day_premium = $policy->premium / 30.42;
                }

                if($one_day_premium != null){
                    $earned_premium = ($one_day_premium)*($no_of_active_days);
                }else{
                    $earned_premium = null;
                }

                if($earned_premium != null){
                    $unearned_premium=($policy->premium)-($earned_premium);
                }else{
                    $unearned_premium = null;
                }
            }else{
                return redirect()->back()->with('error', 'premium is not present');
            }
        return view('admin.policy.creditNote', compact('credit_note','ledger','one_day_premium','policy','unearned_premium','earned_premium','no_of_active_days','policy_cancelled_date','policy_activated_date'));
    }

    public function creditNoteStatement(Request $request,$id,$ledger)
    {
        if(isset($request->earned_premium)){
            $earned_premium = $request->earned_premium;
        }else{
            $earned_premium = null;
        }

        if(isset($request->unearned_premium)){
            $unearned_premium = $request->unearned_premium;
        }else{
            $unearned_premium = null;
        }

        if(isset($request->start_date)){
            $start_date = $request->start_date;
        }else{
            $start_date = null;
        }

        if(isset($request->end_date)){
            $end_date = $request->end_date;
        }else{
            $end_date = null;
        }
        if(isset($request->vat)){
            $vat = $request->vat;
        }else{
            $vat = null;
        }
        if(isset($request->before_vat)){
            $before_vat = $request->before_vat;
        }else{
            $before_vat = null;
        }
        if(isset($request->no_of_days)){
            $no_of_days = $request->no_of_days;
        }else{
            $no_of_days = null;
        }
        $invoice          = Ledger::where('id', $ledger)->first(array('id','invoice_no'));
        $policy           = Policy::where('id',$id)->first(array('id','product_id','customer_id','policyNumber','premium','policyActivatedDate'));
        $vehicleNumber    = Vehicle::where('policy_id', $policy->id)->first(array('vehiclePlate'));
        $policyNumber     = $policy['policyNumber'];
        $product          = Product::where('id',$policy->product_id )->first('line_of_business');
        $customer         = Customer::where('id',$policy['customer_id'])->first();
        $customer_profile = CustomerProfile::where('customer_id',$policy['customer_id'])->first();
        $credit_note      = CreditNote::orderBy('id', 'desc')->first(array('credit_note_no'));
        if($credit_note == NULL)
            $credit_note_no = 'CR'.sprintf('%06d', 1);
        else
            $credit_note_no = 'CR'.str_pad((substr($credit_note->credit_note_no, -6) + 1), 6, '0', STR_PAD_LEFT);
        $array = [
                'customer'            => $customer,
                'customerProfile'     => $customer_profile,
                'policyNumber'        => $policyNumber,
                'earned_premium'      => $earned_premium,
                'unearned_premium'    => $unearned_premium,
                'start_date'          => $start_date,
                'end_date'            => $end_date,
                'vat'                 => $vat,
                'before_vat'          => $before_vat,
                'vehicleNumber'       => $vehicleNumber,
                'credit_note_no_view' => $credit_note_no,
                'product'             => $product->line_of_business,
        ];
        $path = 'CreditNote/'.$earned_premium. $credit_note_no .'.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.credit_note_statement_new',$array);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');

            $credit_note = new CreditNote();
            $credit_note->status                      = 1;
            $credit_note->no_of_days                  = $no_of_days;
            $credit_note->credit_note_no              = $credit_note_no;
            $credit_note->customer_id                 = $customer->id;
            $credit_note->policy_id                   = $policy->id;
            $credit_note->invoice_no                  = $invoice->invoice_no;
            $credit_note->invoice_id                  = $invoice->id;
            $credit_note->transaction_effective_date  = Carbon::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
            $credit_note->transaction_end_date        = Carbon::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');
            $credit_note->earned_premium              = $earned_premium;
            $credit_note->unearned_premium            = $unearned_premium;
            $credit_note->credit_note_file            = $path;
            $credit_note->save();

        // $credit_note = CreditNote::where('policy_id',$id)->where('invoice_no',$invoice->invoice_no)->first();
        // if($credit_note != null){
        //     $credit_note->transaction_effective_date  = Carbon::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
        //     $credit_note->transaction_end_date        = Carbon::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');
        //     $credit_note->earned_premium              = $earned_premium;
        //     $credit_note->unearned_premium            = $unearned_premium;
        //     $credit_note->credit_note_file            = $path;
        //     $credit_note->save();
        // }else{
        //     $credit_note = new CreditNote();
        //     $credit_note->credit_note_no              = $credit_note_no;
        //     $credit_note->customer_id                 = $customer->id;
        //     $credit_note->policy_id                   = $policy->id;
        //     $credit_note->invoice_no                  = $invoice->invoice_no;
        //     $credit_note->invoice_id                  = $invoice->id;
        //     $credit_note->transaction_effective_date  = Carbon::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
        //     $credit_note->transaction_end_date        = Carbon::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');
        //     $credit_note->earned_premium              = $earned_premium;
        //     $credit_note->unearned_premium            = $unearned_premium;
        //     $credit_note->credit_note_file            = $path;
        //     $credit_note->save();
        // }
        // $ledger_delete = Ledger::where('trans_ref',$credit_note->credit_note_no)->orderBy('id', 'desc')->delete();
        // $subledger_ledger_delete = SubLedger::where('trans_ref',$credit_note->credit_note_no)->orderBy('id', 'desc')->delete();

                $ledger            = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
                $balance           = $ledger->balance;
                $record            = array();
        $record['customer_id']     = $policy->customer_id;
        $record['account_id']      = NULL;
        $record['policy_id']       = $policy->id;
        $record['claim_id']        = NULL;
        $record['banking_id']      = NULL;
        $record['account_name']    = NULL;
        $record['accounting_date'] = Carbon::now()->format('Y-m-d');
        $record['trans_type']      = 'Credit Note';
        $record['amount_type']     = NULL;
        $record['trans_ref']       = $credit_note_no;
        $record['orig_trans']      = $credit_note_no;
        $record['unallocated']     = NULL;
        $record['system_date']     = Carbon::now()->format('Y-m-d');
        $record['trans_sub_type']  = NULL;
        $record['eff_date']        = Carbon::now()->format('Y-m-d');
        $record['invoice_file']    = NULL;
        $record['invoice_date']    = NULL;
        $record['invoice_no']      = NULL;
        $record['invoice_amount']  = NULL;
        $record['premium']         = $policy->premium;
        $record['other_charges']   = NULL;
        $record['due_amount']      = NULL;
        $record['pmts_adjust']     = NULL;
        $record['due_date']        = NULL;
        $record['status']          = 'Paid';
                $amount            = str_replace(',', '',$earned_premium);
        $record['debit']           = $amount;
        $record['credit']          = NULL;
        if($balance < 0)
        {
            $record['balance'] = -1 * (str_replace(',', '',number_format(($amount + abs($balance)), 2)));
            $balance = -1 * (str_replace(',', '',number_format(($amount + abs($balance)), 2)));
        } else {
            $record['balance'] = str_replace(',', '',number_format(($balance - $amount), 2));
            $balance = str_replace(',', '',number_format(($balance - $amount), 2));
        }
        $record['balance'] = $balance;
        //----SUB-LEDGER
                   $subRecord         = array();
        $subRecord['customer_id']     = $policy->customer_id;
        $subRecord['account_id']      = NULL;
        $subRecord['policy_id']       = $policy->id;
        $subRecord['claim_id']        = NULL;
        $subRecord['banking_id']      = NULL;
        $subRecord['account_name']    = NULL;
        $subRecord['accounting_date'] = Carbon::now()->format('Y-m-d');
        $subRecord['trans_type']      = 'Credit Note';
        $subRecord['trans_ref']       = $credit_note_no;
        $subRecord['system_date']     = Carbon::now()->format('Y-m-d');
        $subRecord['credit']          = NULL;
        $subRecord['debit']           = number_format($earned_premium, 2);
        $subData  []                  = $subRecord;
        Ledger::insert($record);
        SubLedger::insert($subData);
        return redirect()->back()->with('success', 'Credit note generated Successfully');
      // return redirect()->back()->with(['success' => 'Credit note generated sucessfully','credit_note_data'=>$credit_note_data]);
       //return redirect()->route('admin.policy.creditNoteView', $ledger)->with('credit_note_data',$credit_note_data);
       // return Redirect::route('policy.creditNoteView', $ledger)->with('success', 'Credit note generated Successfully'  . $credit_note_data);
       //return redirect::route('creditNoteView', $ledger)->with('success', 'Credit note generated Successfully', compact('credit_note_data'));
    }

    public function creditSendNoteMail($id)
    {
        $credit_note = CreditNote::where('policy_id',$id)->first('credit_note_file');
        if( $credit_note->credit_note_file != null ){
                $path = Helper::getCloudFrontURL($credit_note->credit_note_file);
        }else{
                $path = null;
        }
        $policy = Policy::where('id',$id)->first(array('id','customer_id','policyNumber'));
        $customer = Customer::where('id',$policy['customer_id'])->first();
        $data = [
            'customer_id' => $customer['id'],
            'firstName'   => $customer['firstName'],
            'lastName'    => $customer['lastName'],
            'attachments' => $path,
        ];
        if($customer->email != null){
            $markdown    = new CreditNoteMail($data);
            $attachments = $path;
            $html        = $markdown->render('Mail.CreditNoteMail',['data'=>$data]);
            event(new \AlphaDirect\Events\SendMail($customer->email,"Alphadirect | document for credit note","",$html,$attachments,[]));

            return redirect()->back()->with('success', 'document send on your email');
        }else{
            return redirect()->back()->with('error', 'Email is not present');
        }
    }

    public function getModalData(Request $request)
    {
        //code to generate OTP and send it to user
        $cellphone = $request->cellphone;
        $customer  = new Customer();
        $otp       = new OTP();

        try {
            if ($customer->checkIfCustomerPhoneNumberExists($cellphone)) {
                $otp_response = $otp->OTPStore($cellphone);  //save otp
                $data         = $otp_response->getData();
                //$sms_status = InfobipSms::send('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code);
                $sms_status = event(new \AlphaDirect\Events\SendSms('+267' . $request->cellphone, 'Alpha Direct, Your Verification Code is:' . ' ' . $data->otp_code));
                $body       = 'Please provide the OTP sent to ';
                return response()->json(['status' => 'success', 'cellphone' => $cellphone, 'body' => $body]);
            } else {
                return response()->json('User does not exist', 401);
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 401);
        }
    }

    public function otpVerification(Request $request){
        $cellphone = $request->phoneNumber;
        $otp_code = $request->otpCode;

        if ($cellphone != null && $otp_code != null) {

            $otp = new OTP(); //instance of OTP model

            $otpValid = $this->checkOTP($cellphone, $otp_code);
            if ($otpValid == true) { //if otp has corresponding cellphone
                return response()->json(['status' => 'success', 'message' => 'OTP verification successful'], 200);

            } else {
                return response()->json(['status' => 'failed', 'message' => 'OTP verification failed'], 401);
            }
        } else {
            return response()->json('Phone number & OTP code is empty', 401);
        }
    }

    public function verifyOTP(Request $request)
    {

        //code to autheticate the Customers OTP and Number
        $cellphone = $request->phoneNumber;
        $otp_code  = $request->otpCode;
        $policyId  = $request->policyId;
        $action    = $request->action;

        try {

            if ($cellphone != null && $otp_code != null) {

                $otp = new OTP(); //instance of OTP model

                //$otpValid = $otp->authenticateOTPCodeUsingOtpCodeAndCellphone($otp_code, $cellphone);
                $otpValid = $this->checkOTP($cellphone, $otp_code);
                if ($otpValid == true) { //if otp has corresponding cellphone
                    //$otpData = $otp->getOTPDataUsingOTPCode($otp_code);
                    $user_id = Customer::where('cellphone', $cellphone)->first()->id;
                    //$sms = InfobipSms::send('+267' . $otpData->cellphone, 'Alpha Direct, Your OTP Has been Verified');
                    if ($action == 2)
                        $check = $this->cancelPolicy($policyId);

                    if ($action == 1)
                        $check = $this->actionToActivate($policyId, 1);

                    if ($check == 1)
                        return response()->json(['status' => 'success', 'message' => 'Policy status updated successfully.'], 200);
                    else
                        return response()->json(['status' => 'failed', 'message' => $check], 402);
                } else {
                    return response()->json(['status' => 'failed', 'message' => 'OTP verification failed'], 401);
                }
            } else {
                return response()->json('Phone number & OTP code is empty', 401);
            }
        } catch (Exception $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function checkOTP($cellphone, $otp)
    {
        $otpData = OTP::where('otp', $otp)->first();
        if ($otpData != null) {
            if ($otpData->cellphone == $cellphone) {
                $deleteOTP = OTP::where('otp', $otp)->delete();
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

//     public function cancelPolicy($id)
//     {
//         try {
//             $policy = Policy::where('id', $id)->first();
//             $currStatus = "";
//             if ($policy->status == 0)
//                 $currStatus = 'In-active';
//             if ($policy->status == 2)
//                 $currStatus = 'Cancelled';

//             $user = Customer::where('id', $policy->customer_id)->first();
//             $policy->status = 2;
//             $saved = $policy->save();

// //            //Policy Cancelled By:
// //            if ($policy->save()) {
// //                $feedback = new CustomerFeedback();
// //                $feedback->user_id = auth()->user()->id;
// //                $feedback->save();
// //            }

//             $update = $this->updatePolicyDates($policy->policyNumber, 2);

//             $banking = CustomerBanking::where('policy_id', $policy->id)->first();
//             if ($banking->billing == 'VCS') {

//                 $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();

//                 if($transctionsRow && $transctionsRow->referenceNumber){
//                     $referenceNumber = $transctionsRow->referenceNumber;
//                     $vcs = new PaymentController;
//                     $vcs->suspendTransactionOnVCS($referenceNumber);
//                 }

//             } elseif ($banking->billing == 'RealPay') {

//                 $check = RealpayClientContracts::where('policy_id',$policy->id)
//                     ->orderBy('id','desc')
//                     ->get();

//                 if($check == null){
//                     $check = RealpayPaymentRequest::where('policy_id', $policy->id)->get();
//                 }

//                foreach ($check as $key => $check) {
//                     if (isset($check)) {
//                         $contractNumber = $check->contractNumber;
//                     } else {
//                         $contractNumber = null;
//                     }
//                     if ($check != null) {
//                         $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
//                         $addLog = $log->logEvent($policy->id, 2);

//                         if ($addLog) {
//                             $request                           = new RealpayCancelRequests();
//                             $request->policy_id                = $policy->id;
//                             $request->leftout_premium_contract = null;
//                             $request->contract                 = $contractNumber;
//                             $request->cancel_status            = 0;
//                             $request->save();
//                             // dd('1');
//                             return 1;
//                         } else {
//                             //dd('2');
//                             return 1;
//                         }
//                     } else {
//                         //dd('3');
//                         return 1;
//                     }
//                }
//             } else {
//                 $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();
//                 if($transctionsRow && $transctionsRow->referenceNumber){
//                     $referenceNumber = $transctionsRow->referenceNumber;
//                     $vcs = new PaymentController;
//                     $vcs->suspendTransactionOnVCS($referenceNumber);
//                 }
//                 //return response()->json(['success' => 0, 'Message' => 'Banking details not found'], 401);
//             }

//             $data = [
//                 "token"            => ScheduleTransaction::where('policy_number', $policy->policyNumber)->where('status', 1)->value('token'),
//                 "policy_number"    => $policy->policyNumber,
//                 "CompanyRef"       => env('COMPANY_REF'),
//                 "customer_id"      => $policy->customer_id,
//             ];

//             if($data['token'] != null )
//             {
//                 CancelTokenEvent::dispatch($data);
//             }

//             if(ScheduleTransaction::where('policy_number', $data['policy_number'])->exists())
//             {
//                 CancelScheduleTransactionEvent::dispatch($data);
//             }

//             if (Auth::check()) {
//                 activity('Policy Status')
//                     ->performedOn($policy)
//                     ->causedBy(User::where('id', auth()->user()->id)->first())
//                     ->log('Policy Status Updated : ' . $currStatus . ' to CANCELLED');
//             }

//             //$policy_number = str_replace('#', '', $policy->policyNumber);
//             //sms
//             if($user->cellphone != null){
//                 $sms = new SmsMessaging();
//                 $sms->SendSMSEmailPolicyCancelled($user->cellphone,$user->firstName,$policy->policyNumber);
//                 // $sms = $sms->SendSMSEmailPolicyCancelled(24,$user->cellphone,$policy->policyNumber,$user->firstName);
//              }

//             activity('Policy Cancel SMS')
//                 ->performedOn($policy)
//                 ->causedBy(User::where('id', auth()->user()->id)->first())
//                 ->log('SMS send');

//             //mail
//             if ($user->email != null) {
//                 $data              = new \stdClass();
//                 $data->user_id     = null;
//                 $data->policy_id   = $policy->id;
//                 $data->customer_id = $user->id;
//                 $data->hook        = 'cancel_policy';
//                 $data->attachment  = null;
//                 $emailTemplate     = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
//                 $markdown          = new MailTemplate($data);
//                 $html              = $markdown->render('Mail.mailTemplate',['data'=>$data]);
//                 event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
//                // Mail::to($user->email)->send(new MailTemplate($data));
//             }

//             activity('Policy Cancel Email')
//                 ->performedOn($policy)
//                 ->causedBy(User::where('id', auth()->user()->id)->first())
//                 ->log('Email send');

//             if ($policy->save())
//                 $inv_no = Helper::ledgerStore($user->id, 'POLICY', $policy->id, $policy->product_id, 'CANCEL');

//             return 1;
//         } catch (Exception $e) {
//             return $e;
//         }
//     }


    public function cancelPolicy($id)
    {
        try {
            $policy = Policy::where('id', $id)->first();
            $currStatus = "";
            if ($policy->status == 0)
                $currStatus = 'In-active';
            if ($policy->status == 2)
                $currStatus = 'Cancelled';

            $user = Customer::where('id', $policy->customer_id)->first();

//            //Policy Cancelled By:
//            if ($policy->save()) {
//                $feedback = new CustomerFeedback();
//                $feedback->user_id = auth()->user()->id;
//                $feedback->save();
//            }

            $update = $this->updatePolicyDates($policy->policyNumber, 2);

          /*  $banking = CustomerBanking::where('policy_id', $policy->id)->first();
            if ($banking->billing == 'VCS') {

                $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();

                if($transctionsRow && $transctionsRow->referenceNumber){
                    $referenceNumber = $transctionsRow->referenceNumber;
                    $vcs = new PaymentController;
                    $vcs->suspendTransactionOnVCS($referenceNumber);
                }

                $policy->status = 2;
                $saved = $policy->save();

            }elseif (NgeniusTransection::where('policy_number',$policy->policyNumber)->orderby('id','desc')->where('status',1)->exists()) {
                $policyNumber = $policy->policyNumber;
                $ngenius = new NgeniusPaymentController();
                $cancel =  $ngenius->NgeniusRecurringDeletedata2($policyNumber);

               if($cancel == 2){
                return 2;
               }else{
                if($cancel == 1){
                    $policy->status = 2;
                     $policy->save();
                }
            }


            }
             elseif ($banking->billing == 'RealPay') {
                $check = RealpayClientContracts::where('policy_id',$policy->id)
                    ->orderBy('id','desc')
                    ->get();

                if($check->isEmpty()){
                    $check = RealpayContractDetails::where('ClientNumber', $policy->policyNumber)->get();
                }

                if($check->isEmpty()){
                    $check = RealpayPaymentRequest::where('policy_id', $policy->id)->get();
                }

               foreach ($check as $key => $check) {
                    if (isset($check)) {
                        $contractNumber = $check->contractNumber;
                    } else {
                        $contractNumber = null;
                    }

                    if ($check != null) {
                        $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                        // $addLog = $log->logEvent($policy->id, 2);
                        if ($policy->product_id == 3) {
                            $addLog = $log->cancelRealpayContract($policy->id);
                        } else {
                            $addLog = $log->cancelRealpayContractsForInstProduct($policy->id);
                        }

                            if ($addLog) {
                            $request                           = new RealpayCancelRequests();
                            $request->policy_id                = $policy->id;
                            $request->leftout_premium_contract = null;
                            $request->contract                 = $contractNumber;
                            $request->cancel_status            = 0;
                            $request->save();
                            // dd('1');

                            $policy->status = 2;
                            $saved = $policy->save();

                            // return 1;
                        } else {
                            //dd('2');
                            // return 1;
                        }
                    } else {
                        //dd('3');
                        // return 1;
                    }
               }
            } else {
                $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();

                if($transctionsRow && $transctionsRow->referenceNumber){
                    $referenceNumber = $transctionsRow->referenceNumber;
                    $vcs = new PaymentController;
                    $vcs->suspendTransactionOnVCS($referenceNumber);
                }

                $policy->status = 2;
                $saved = $policy->save();

                //return response()->json(['success' => 0, 'Message' => 'Banking details not found'], 401);
            }

            $data = [
                "token"            => ScheduleTransaction::where('policy_number', $policy->policyNumber)->where('status', 1)->value('token'),
                "policy_number"    => $policy->policyNumber,
                "CompanyRef"       => env('COMPANY_REF'),
                "customer_id"      => $policy->customer_id,
            ];


            if($data['token'] != null )
            {
                CancelTokenEvent::dispatch($data);
            }


            if(ScheduleTransaction::where('policy_number', $data['policy_number'])->exists())
            {
                CancelScheduleTransactionEvent::dispatch($data);
            }

            */
            $policy->status = 2;

            $saved = $policy->save();
            $cancelstatus =  $this->CancelPaymentsForPolicy($policy);
            $action_user = null;
            $action_customer = $policy->customer_id;
            event(new \AlphaDirect\Events\policyLifecycle($policy->id,"Cancel",$action_user,$action_customer));
            if (Auth::check()) {
                activity('Policy Status')
                    ->performedOn($policy)
                    // ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy Status Updated : ' . $currStatus . ' to CANCELLED');
            }

            //$policy_number = str_replace('#', '', $policy->policyNumber);
            //sms
            if($user->cellphone != null){
                $sms = new SmsMessaging();
                $sms->SendSMSEmailPolicyCancelled($user->cellphone,$user->firstName,$policy->policyNumber);
                // $sms = $sms->SendSMSEmailPolicyCancelled(24,$user->cellphone,$policy->policyNumber,$user->firstName);

                // $dataCreatePolicy =[
                //     "type"=>"template",
                //     "subType"=>"policy_cancelled",
                //     "mobileNumber"=>'267'.$user->cellphone ,
                //     "policyNumber"=>$policy->policyNumber
                // ];
                // $WhatsAppController= new WhatsAppController();
                // $WhatsAppController->sendMessage($dataCreatePolicy);
            }

            activity('Policy Cancel SMS')
                ->performedOn($policy)
                // ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('SMS send');

            //mail
            if ($user->email != null) {
                $data              = new \stdClass();
                $data->user_id     = null;
                $data->policy_id   = $policy->id;
                $data->customer_id = $user->id;
                $data->hook        = 'cancel_policy';
                $data->attachment  = null;
                $emailTemplate     = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown          = new MailTemplate($data);
                $html              = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
               // Mail::to($user->email)->send(new MailTemplate($data));
            }

            activity('Policy Cancel Email')
                ->performedOn($policy)
                // ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Policy Cancel Email send');

            if ($policy->save())
                $inv_no = Helper::ledgerStore($user->id, 'POLICY', $policy->id, $policy->product_id, 'CANCEL');

            return 1;
        } catch (Exception $e) {
            return $e;
        }
    }

    /*
     * Pass data through ajax call
     * for sub ledger tab under policy edit
     * param: policy id ($id)
     * @return mixed
     */ // paymentTransactions
    public function paymentTransactions($id)
    {
        $payTrx = PaymentTransaction::where('policyNumber', $id)->where('status','!=','CANCELLED')->get();
        return DataTables::of($payTrx)

            ->editColumn('contractNumber', function ($payTrx) {
                $contractNumber = 'N/A';
                if (isset($payTrx->paymentMethod) && $payTrx->paymentMethod == 'RealPay') {
                    $realpayInstalment = RealpayContractInstallments::where('InstalmentReferenceNumber',$payTrx->referenceNumber)->first();
                    if (isset($realpayInstalment)) {
                        $contractNumber = $realpayInstalment->contractNumber;
                    } else {
                        $contractNumber = 'N/A';
                    }

                }
                return $contractNumber;
            })

            ->editColumn('paymentFrequency', function ($payTrx) {
                if ($payTrx->paymentFrequency == 1) {
                    return  'Monthly';
                } elseif ($payTrx->paymentFrequency == 2) {
                    return  'Three Instalments';
                } elseif ($payTrx->paymentFrequency == 3) {
                    return  'Annual';
                } else {
                    $policy = Policy::where('policyNumber', $payTrx->policyNumber)->first(array('premium_freq'));
                    if ($policy->premium_freq == 1) {
                        return  'Monthly';
                    } elseif ($policy->premium_freq == 2) {
                        return  'Three Instalments';
                    } elseif ($policy->premium_freq == 3) {
                        return 'Annual';
                    }else {
                        return 'Monthly'; // Monthly on null
                    }
                }
            })

            ->editColumn('reason', function ($payTrx) {
                return $payTrx->reason;
            })

            // ->editColumn('paymentDate', function ($payTrx) {
            //     if($payTrx->paymentDate != null){
            //         return $payTrx->paymentDate;
            //         // return  Carbon::createFromFormat('Y/m/d H:i:s A', $payTrx->paymentDate)->format('Y-m-d H:i:s');
            //         //   Carbon::parse(str_replace("/", "-", $payTrx->paymentDate))->format('Y-m-d H:i:s');
            //     }
            //     else {
            //         return '-';
            //     }
            // })

            ->editColumn('refunded_by', function ($payTrx) {
                $return = ucfirst($payTrx->refunded_by);
                return $return;
            })

            ->editColumn('actions', function ($payTrx) {
                $action = '';
                if ($payTrx->payment_proof_link) {
                    $action = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($payTrx->payment_proof_link) . '" target= "_blank" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View proof of payment">
                                <i class="la la-eye"></i>
                            </a>';
                }
                if($payTrx->CompanyRef != 'Reversed' && $payTrx->is_ledger == 1)
                {
                    $action .= '<a href="" value="'.$payTrx->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md transaction-log-confirm-delete" title="Delete">
                            <i class="la la-trash"></i></a>';
                }

                return  $action;
            })
            ->editColumn('paymentMethod', function ($payTrx) {
                if ($payTrx->paymentMethod == 'orangeMoney') {
                    return  'Orange USSD';
                } else {
                    return $payTrx->paymentMethod;
                }
            })
            ->editColumn('paymentLoggedBy', function ($payTrx) {
                if ($payTrx->paymentLoggedBy != null) {
                    $user = User::where('id',$payTrx->paymentLoggedBy)->first();
                    return $user->firstName.' '.$user->lastName;
                } else {
                    return '-';
                }
            })
            ->editColumn('cashRecipient', function ($payTrx) {
                if ($payTrx->cashRecipient != null) {
                    return $payTrx->cashRecipient;
                } else {
                    return '-';
                }
            })
            ->editColumn('created_at', function ($payTrx) {
                if ($payTrx->created_at != null) {
                    return  Carbon::parse($payTrx->created_at)->format('Y-m-d H:i');
                } else {
                    return '-';
                }
            })

            ->rawColumns(
                [
                    'contractNumber',
                    'paymentFrequency',
                    'actions',
                    'paymentMethod',
                    'paymentLoggedBy',
                    'cashRecipient',
                    // 'paymentDate'
                ]
            )
            ->make(true);
    }

    public function subLedgerData($id)
    {
        $subledger_graphite = SubLedger::where('policy_id', $id)->get();
        $subledger_archive = SubledgerArchive::where('policy_id', $id)->get();
        $merged = $subledger_archive->merge($subledger_graphite);
        $ledger = $merged->all();

        return DataTables::of($ledger)
            ->editColumn('credit', function ($ledger) {
                if ($ledger->credit == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-success">P' . $ledger->credit . '</span>';
                    return $cred;
                }
            })
            ->editColumn('debit', function ($ledger) {
                if ($ledger->debit == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">P0.00</span>';
                    return $cred;
                } else {
                    $cred = '<span class="kt-font-bold kt-font-danger">P' . $ledger->debit . '</span>';
                    return $cred;
                }
            })
            ->rawColumns(['credit', 'debit'])
            ->make(true);
    }

    /*
     * Pass data through ajax call
     * for activity tab under policy edit
     * param: policy id ($id)
     * @return mixed
     */
    public function activity($id)
    {
        $policydetails = Policy::where('id',$id)->first();
        $profile       = CustomerProfile::where('customer_id', $policydetails->customer_id)->first('id');
        $vehicle       = Vehicle::where('policy_id',$id)->first('id');
        $kyc           = KYC::where('customer_id',$policydetails->customer_id)->first('id');

        $activity = \AlphaDirect\Models\Audits::orderBy('created_at', 'desc')
        //->wherein('auditable_id', [$id,$policydetails->customer_id,$profile,$vehicle,$kyc])
        ->where('policy_id',$policydetails->id)
        ->orwhere('policy_number',$policydetails->policyNumber)
        ->get(array('id','agent_id','user_id','user_agent', 'auditable_id', 'old_values', 'new_values','tags','url','ip_address','created_at'));
        // $activity = \AlphaDirect\Models\Audits::orderBy('created_at', 'desc')->where('auditable_id', $id)
        //     ->get(array('id','user_id', 'auditable_id', 'old_values', 'new_values','url','ip_address','user_agent', 'created_at'));
        return DataTables::of($activity)
            ->editColumn('user_id', function ($activity) {
                if($activity->user_id != null){
                    $user = User::where('id', $activity->user_id)->first();
                    return $activity ? $user->firstName . ' ' . $user->lastName : '-';
                }
                elseif($activity->agent_id != null){
                    $user = User::where('id', $activity->agent_id)->first();
                    return $activity ? '(Agent) '.' '.$user->firstName . ' ' . $user->lastName : '-';
                }
                else{
                    return 'NA';
                }
            })
            ->editColumn('old_values', function ($activity) {
                if ($activity->old_values){
                   $data = $activity->old_values;
                   return $json_string = json_encode(json_decode($data), JSON_PRETTY_PRINT);
                }
                else{
                    return 'NA';
                }
            })
            ->editColumn('new_values', function ($activity) {
                if ($activity->new_values){
                   $data = $activity->new_values;
                   return $json_string = json_encode(json_decode($data), JSON_PRETTY_PRINT);
                }
                else{
                    return 'NA';
                }
            })
            ->editColumn('tag', function ($activity) {
                if ($activity->tags){
                   $tag = $activity->tags;
                   return $tag;
                }
                else{
                    return 'NA';
                }
            })
            ->editColumn('created_at', function ($activity) {
                return $activity->created_at->format('d-m-Y H:i');
                // return $activity->created_at->diffForHumans();
            })
            ->rawColumns(['user_id','old_values','new_values','tag','created_at'])
            ->make(true);
    }
    /**`
     * Show a list of all the policies.
     *
     * @return View
     */
    public function index()
    {
        if (auth::user()->hasPermissionTo('policy-list')) {
            $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
            $policies = Policy::all();
            $agents = Policy::join('users', 'users.id', 'policies.agent_id')
                ->where('users.active', 1)
                ->where('policies.agent_id', '!=', 'null')
                ->groupBy('policies.agent_id')
                ->get();
            // Show the page
            return view('admin.policy.index', compact('products', 'policies', 'agents'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**`
     * Show a specific policy view page.
     * param: policy id ($id)
     * @return View
     */
    public function view(Request $request, $id)
    {
        $policy = Policy::findorFail($id);
        $productPlan = Productplan::where('id', $policy->plan_id)->first(array('name', 'sum_assured'));
        $user = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first(array('id', 'firstName', 'middleName', 'lastName', 'email', 'cellphone', 'gender'));
        $product = Product::where('id', $policy->product_id)->first(array('name', 'is_motor_items', 'premium_type_id', 'region_id'));
        $productFactors = FactorMain::with('value')->where('product_id', $policy->product_id)->get(array('id', 'name', 'type'));
        foreach ($productFactors as $productFactor) {
            $policyFactors = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $productFactor->id)->get(array('factor_value_id', 'value_name', 'name'));
            if ($productFactor->type == 'Input Field') {
                $productFactor->policyFactors = $policyFactors->pluck('value_name')->toArray();
            } else {
                $productFactor->policyFactors = $policyFactors->pluck('factor_value_id')->toArray();
            }

            $productFactor->policyFactorsValueName = $policyFactors->pluck('value_name')->toArray();
        }
        $members       = PolicyMember::where('policy_id', $policy->id)->get();
        $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
        $policyCover   = PolicyCoverage::where('policy_id', $policy->id)->get(array('id', 'main', 'coverage_value', 'discount', 'type', 'value'));
        $banking       = CustomerBanking::where('policy_id', $policy->id)->first();
        $kyc           = KYC::where('customer_id', $policy->customer_id)->first();

        if ($policy->has_vehicle) {
            $vehicle = Vehicle::where('policy_id', $policy->id)->first();
            $vehicleMakes = \Illuminate\Support\Facades\DB::table('tb_prmotormakemodels')
                ->selectRaw('DISTINCT s_Make')
                ->pluck('s_Make');
            if ($vehicle && $vehicle->make != null) {
                $vehicleModels = DB::table('tb_prmotormakemodels')->where('s_Make', $vehicle->make)
                    ->selectRaw('DISTINCT s_Variant')
                    ->get(array('s_Variant'));
            } else {
                $vehicleModels = DB::table('tb_prmotormakemodels')
                    ->selectRaw('DISTINCT s_Variant')
                    ->get(array('s_Variant'));
            }
            $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
        }
        $agents = User::role('Agent')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
        if ($policy->agent_id != null) {
            $agent_name = User::leftJoin('agencies', 'agencies.id', 'users.agency_id')
                ->where('users.id', $policy->agent_id)
                ->first(array('users.firstName', 'users.lastName', 'agencies.id as agency_id', 'agencies.status as agency_status', 'agencies.name as agency_name'));
        }
        $policyMotorItems = PolicyMotorItems::where('policy_id', $policy->id)->get(array('id', 'item_name', 'item_value', 'policy_id'));
        $count            = count($policyMotorItems);
        $motor_items      = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));
        $transaction      = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status', 'referenceNumber'));
        $regionVat        = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));

            if ($product->id != 3)
                $premium = $policy->premium /*round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2)*/;
            else
                $premium = $policy->premium /*round(($policy->premium + $policy->vat), 2)*/;
            //This is special requirement added only for motor comprehensive product where we are storing policy premium
            //without VAT and for Instant insurance ,we are storing premium + VAT.

        } else {
            $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
            $policy->premium = $premium;
            $policy->vat = $request->get('premium') * ($regionVat / 100);
            $policy->sum_assured = $request->sum_assured;
        }
        return view('admin.policy.view', compact('vehicleMakes', 'vehicleModels', 'claims', 'productPlan', 'premium', 'policyFactors', 'count', 'agent_name', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name', 'policyMotorItems', 'motor_items', 'transaction'));
    }

    /**`
     * generates unique user id
     *
     * @return mixed
     */
    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            // 16 bits for "time_mid"
            Helper::gen_ustring(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff),
            Helper::gen_ustring(0, 0xffff)
        );
    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
//    public function data(Request $request)
//    {
//
//        ## Read value
//        $draw = $request->get('draw');
//        $start = $request->get("start");
//        $rowperpage = $request->get("length"); // Rows display per page
//
//        $columnIndex_arr = $request->get('order');
//        $columnName_arr = $request->get('columns');
//        $order_arr = $request->get('order');
//        $search_arr = $request->get('search');
//        $pName = null;
//        $columnIndex = $columnIndex_arr[0]['column']; // Column index
//        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
//        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
//        $searchValue = trim($search_arr['value']); // Search value
//
//        // Total records
//        $totalRecords = Policy::select('count(*) as allcount')->count();
//        # DB::enableQueryLog();
//        // Fetch records
//        $records = Policy::orderBy('id', 'DESC')
//            ->leftJoin('customer', 'customer.id', 'policies.customer_id')
//            ->leftJoin('products', 'products.id', 'policies.product_id')
//            ->leftJoin('vehicle', 'vehicle.policy_id', 'policies.id');
//
//        if ($searchValue != null) {
//            $records->where('policies.id', 'like', '%' . $searchValue . '%')
//                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
//                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
//                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
//                ->orWhere('vehicle.vehiclePlate', 'like', '%' . $searchValue . '%')
//                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'), 'like', '%' . $searchValue . '%')
//                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'), 'like', '%' . $searchValue . '%');
//            #->orWhere('customer.cellphone', 'like', '%' .$searchValue . '%');
//        }
//
//        if ($request->policyStatus_filter != -1 /*&& $request->product_filter == -1*/) {
//            $records->where('policies.status', $request->policyStatus_filter);
//        }
//
//        if ((Auth::user()->hasRole('Manager') || Auth::user()->hasRole('Super Admin')) == false) {
//            # $records->where('policies.agent_id',auth()->user()->id)->orWhere('policies.agent_id','=',null);
//            /*   $records->where(function ($query) {
//                $query->where('policies.agent_id', auth()->user()->id)
//                    ->orWhere('policies.agent_id','=',null);
//            }); */
//        }
//
//        if ($request->product_filter != -1 /*&& $request->policyStatus_filter == -1*/) {
//            $records->where('policies.product_id', $request->product_filter);
//        }
//
//        if ($request->cellphone_filter) { // filter Policy by cellphone number, policy_cellphone is the ID of the policy
//            $records->where('customer.cellphone', 'like', '%' . $request->cellphone_filter . '%');
//        }
//        /*if ($request->reference_filter) { // filter Policy by reference number
//            $records->where('vcs_new_transactions.reference','like', '%' . $request->reference_filter  . '%');
//        }*/
//
//        //        if ($request->policy_filter != -1) {
//        //            if($request->policy_filter == 1)
//        //                $records->where('policies.agent_id', auth()->user()->id);
//        //
//        //            if($request->policy_filter == 2)
//        //                $records->whereNull('policies.agent_id');
//        //
//        //            if($request->policy_filter == 3)
//        //                $records->whereNotNull('policies.agent_id');
//        //        }
//        if ($request->agent_filter != -1) {
//            $records->where('policies.agent_id', $request->agent_filter);
//        }
//        if (auth::user()->hasPermissionTo('policy-Full List') == false && $searchValue == null) {
//            if (auth::user()->hasPermissionTo('policy-Only with Agents') == true)
//                $records->whereNotNull('policies.agent_id');
//
//            if (auth::user()->hasPermissionTo('policy-Only with No Agents') == true)
//                $records->whereNull('policies.agent_id');
//
//            if (auth::user()->hasPermissionTo('policy-Own Policy') == true)
//                $records->where('policies.agent_id', auth()->user()->id);
//
//            if (auth::user()->hasPermissionTo('policy-own and with N/A') == true) {
//                $records->where(function ($query) {
//                    $query->whereNull('policies.agent_id')
//                        ->orWhere('policies.agent_id', auth()->user()->id);
//                });
//            }
//            //$records->where('policies.agent_id',auth()->user()->id)->orWhereNull('policies.agent_id')->orWhere('policies.agent_id','0');
//        }
//
//        if ($request->lead_agent != '-1') {
//            $records->where('policies.leadAgentID', $request->lead_agent);
//        }
//        /*$records->where('policies.leadSource', 'like', '%LiveQuote%')
//            ->orWhere('policies.leadSource', 'like', '%Graphite%')
//            ->orWhere('policies.leadSource', 'like', '%start.alphadirect.co.bw%')
//            ->orWhere('policies.leadSource', null);*/
//
//        //        if($searchValue != null){
//        //            $totalRecordswithFilter = Policy::join('customer', 'customer.id', 'policies.customer_id')
//        //                ->leftJoin('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
//        //                ->join('products', 'products.id', 'policies.product_id')
//        //                ->leftJoin('vcs_new_transactions', 'vcs_new_transactions.policyNumber', 'policies.policyNumber')
//        //                ->where('policies.id', 'like', '%' . $searchValue . '%')
//        //                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
//        //                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
//        //                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
//        //                ->orWhere('products.name', 'like', '%' . $searchValue . '%')
//        //                /*->orWhere('vcs_new_transactions.reference','like', '%' .$searchValue . '%')*/
//        //                ->select('count(*) as allcount')
//        //                ->count();
//        //        } else {
//        //            $totalRecordswithFilter = $records->count();
//        //        }
//
//        $totalRecordswithFilter = $records->count();
//
//        $records = $records->skip($start)
//            ->take($rowperpage)
//            ->get(
//                [
//                    'policies.id',
//                    'policies.customer_id',
//                    'policies.product_id',
//                    'policies.agent_id',
//                    'policies.has_vehicle',
//                    'policies.has_member',
//                    'policies.policyNumber',
//                    'policies.status',
//                    'policies.created_at'
//                ]
//            );
//        # dd(DB::getQueryLog());
//        $records = json_decode($records, true);
//
//        $data_arr = array();
//        $sno = $start + 1;
//        foreach ($records as $record) {
//            if ($record['id'])
//                $id = $record['id'];
//            else
//                $id = 'N/A';
//
//            if ($record['policyNumber'] && $id != 'N/A') {
//                $view = '<a href="' . route('admin.policy.policyView', $id) . '" target="_blank">
//                        ' . $record['policyNumber'] . '
//                        </a>';
//            } else {
//                $view = 'N/A';
//            }
//
//            if ($record['customer_id'] != null) {
//                $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
//            }
//
//            if ($trans != null && $trans['referenceNumber'] != null) {
//                $referenceNumber = $trans['referenceNumber'];
//            } else {
//                $referenceNumber = "Payment reference not generated";
//            }
//
//            if ($record['customer_id'] != null) {
//                $customer = Customer::where('id', $record['customer_id'])->first(array('firstName', 'middleName', 'lastName', 'cellphone','customer_category'));
//                if ($customer != null) {
//                    if($customer->customer_category == 1){
//                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"><span class="graydot"></span>&nbsp&nbsp' . ucwords($customer->firstName) . ' ' . ucwords($customer->middleName) . ' ' . ucwords($customer->lastName) . '</a>';
//                    }elseif($customer->customer_category == 2){
//                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> <span class="blackdot"></span>&nbsp&nbsp' . ucwords($customer->firstName) . ' ' . ucwords($customer->middleName) . ' ' . ucwords($customer->lastName) . '</a>';
//                    }else{
//                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> <span class="greendot"></span>&nbsp&nbsp' . ucwords($customer->firstName) . ' ' . ucwords($customer->middleName) . ' ' . ucwords($customer->lastName) . '</a>';
//                    }
//                } else {
//                    $name = 'N/A';
//                }
//            } else {
//                $name = 'N/A';
//            }
//
//            if ($record['policyNumber'])
//                $policyNumber = $record['policyNumber'];
//            else
//                $policyNumber = 'N/A';
//
//            $cell = isset($customer->cellphone) ? $customer->cellphone : "N/A";
//
//            if ($record['product_id'] != null) {
//                $product = Product::where('id', $record['product_id'])->first(array('name'));
//                if ($product)
//                    $pName = $product->name;
//            } else {
//                $pName = 'N/A';
//            }
//            $productName = $pName;
//
//
//            if ($record['agent_id'] != null) {
//                $agent = User::where('id', $record['agent_id'])->first(array('firstName','lastName'));
//                if ($agent)
//                    $pAgent = ucwords($agent->firstName).'  '.ucwords($agent->lastName);
//            } else {
//                $pAgent = 'N/A';
//            }
//            $agentName = $pAgent;
//
//
//            $status = '';
//            $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
//            if ($record['status'] == 1) {
//                $status .= '<span class="kt-font-bold kt-font-brand">Activated</span>';
//            } elseif ($record['status'] == 2) {
//                $status .= '<span class="kt-font-bold kt-font-danger">Cancel</span>';
//            } else {
//                $status .= '<span class="kt-font-bold kt-font-focus">Deactivated</span>';
//            }
//
//            $payment = '';
//            $banking = CustomerBanking::where('policy_id', $record['id'])->first(array('billing'));
//            if ($trans && $trans->paymentMethod != null) {
//                if ($trans->paymentMethod == 'orangeMoney')
//                    $payment = 'Orange USSD';
//                else
//                    $payment = $trans->paymentMethod;
//            } else {
//                $data = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));
//
//                if ($data && $data->referenceNumber)
//                    $referenceNumber = $data->referenceNumber;
//                else
//                    $referenceNumber = 'Reference number not generated';
//
//                if ($data && $data->realPayTransaction_id != null) {
//                    $payment = 'RealPay';
//                } elseif ($data && $data->vcsTransaction_id != null) {
//                    $payment = 'VCS';
//                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null) {
//                    $payment = 'VCS';
//                } elseif ($data && $data->orangeTransaction_id != null && $data->referenceNumber != null) {
//                    $payment = 'Orange USSD';
//                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null && $data->status == 0) {
//                    $payment = 'Payment not initiated';
//                } else {
//                    if ($banking && $banking->billing)
//                        if ($banking->billing == 'orangeMoney')
//                            $payment = 'Orange USSD';
//                        else
//                            $payment = $banking->billing;
//                    else
//                        $payment = 'Payment method not found';
//                }
//            }
//
//            $vehicle_plate = Vehicle::where('policy_id', $record['id'])->value('vehiclePlate');
//            if ($vehicle_plate == null) {
//                $vehicle_plate = 'N/A';
//            }
//            $kyc = KYC::where('customer_id', $record['customer_id'])->first();
//
//            if ($kyc && $kyc->compliance == 1) {
//                $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
//            } elseif ($kyc && $kyc->compliance == 0) {
//                $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">KYC Verification Pending </span>';
//            } elseif ($kyc && $kyc->compliance == 2) {
//                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
//            } else {
//                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Status not found</span>';
//            }
//
//            if ($trans != null) {
//                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
//                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
//                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
//                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
//                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
//                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
//                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
//                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
//                } else {
//                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
//                }
//            } else {
//                $trans = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));
//
//                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
//                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
//                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
//                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
//                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
//                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
//                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
//                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
//                } elseif ($trans != null && strtoupper($trans['status']) == "0") {
//                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
//                } elseif ($trans != null && $trans['vcsTransaction_id'] == null && $trans['realPayTransaction_id'] == null && $trans['referenceNumber'] != null && strtoupper($trans['status']) == "0") {
//                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
//                } else {
//                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
//                }
//            }
//
//            $actions = '';
//            if (auth::user()->hasPermissionTo('policy-edit')) {
//                $actions .= '<a href="' . route('admin.policy.edit', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
//                                <i class="la la-edit"></i>
//                            </a>';
//            } else {
//                $actions .= '<a href="' . route('admin.policy.policyView', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
//                                <i class="la la-eye"></i>
//                            </a>';
//            }
//
//            if (auth::user()->hasPermissionTo('policy-archive') && $record['status'] != 1) {
//                $actions .= '<a href="#" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-archive" value="'.$id.'" title="Archive">
//                                <i class="la la-archive"></i>
//                            </a>';
//            }
//
////            ' . route('admin.policy.archive', $id) . '
//
//            if (auth::user()->hasPermissionTo('claim-create')) {
//                if ($record['status'] == 1 && $kyc && $kyc->compliance == 1) {
//                    if ($record['has_vehicle'] == 1 && $record['has_member'] == 1) {
//                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass life accident key_loss claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
//                    } elseif ($record['has_vehicle'] == 1 && $record['has_member'] == 0) {
//                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass accident key_loss claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
//                    } elseif ($record['has_vehicle'] == 0 && $record['has_member'] == 1) {
//                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md life claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
//                    } elseif ($record['has_vehicle'] == 0 && $record['has_member'] == 0) {
//                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md cellphone claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
//                    }
//                }
//            }
//            if (auth()->user()->hasRole('Super Admin')) {
//                $urlValue = \Config::get('values.graphite_url');
//                $stringURLArray = array(env('LOC_ENV'), env('DEV_ENV'), env('QA_ENV'));
//                if (in_array($urlValue, $stringURLArray)) {
//                    $transaction = $trans = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
//                    if ($transaction == null || $transaction['status'] != 'SUCCESS') {
//                        $actions .= '<button value="' . $id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md change_payment_status" title="Change Payment Status">
//                                <i class="la la-caret-up"></i>
//                                </button>';
//                    }
//                }
//            }
//
//            $data_arr[] = array(
//                "id"              => $id,
//                "view"            => $view,
//                "referenceNumber" => $referenceNumber,
//                "name"            => $name,
//                "cellphone"       => $cell,
//                "product_name"    => $productName,
//                "agentName"       => $agentName,
//                "status"          => $status,
//                "payment_method"  => $payment,
//                "vehicle_plate"   => ucfirst($vehicle_plate),
//                "created_at"      => $record['created_at'],
//                "actions"         => $actions
//
//            );
//        }
//
//        $policies = array(
//            "draw" => intval($draw),
//            "iTotalRecords" => $totalRecords,
//            "iTotalDisplayRecords" => $totalRecordswithFilter,
//            "aaData" => $data_arr
//        );
//
//        echo json_encode($policies);
//
//        exit;
//    }

    public function data(Request $request)
    {

        ## Read value
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');
        $pName = null;
        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = trim($search_arr['value']); // Search value

        // Total records
        $totalRecords = Policy::select('count(*) as allcount')->count();
        # DB::enableQueryLog();
        // Fetch records
        $records = Policy::orderBy('policies.id', 'DESC')
            ->leftJoin('customer', 'customer.id', 'policies.customer_id')
            ->leftJoin('products', 'products.id', 'policies.product_id')
            ->leftJoin('vehicle', 'vehicle.policy_id', 'policies.id');

        if ($searchValue != null) {
            $records->where('policies.id', 'like', '%' . $searchValue . '%')
                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
                ->orWhere('vehicle.vehiclePlate', 'like', '%' . $searchValue . '%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'), 'like', '%' . $searchValue . '%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'), 'like', '%' . $searchValue . '%');
            #->orWhere('customer.cellphone', 'like', '%' .$searchValue . '%');
        }

        if ($request->policyStatus_filter != -1 /*&& $request->product_filter == -1*/) {
            $records->where('policies.status', $request->policyStatus_filter);
        }

        if ((Auth::user()->hasRole('Manager') || Auth::user()->hasRole('Super Admin')) == false) {
            # $records->where('policies.agent_id',auth()->user()->id)->orWhere('policies.agent_id','=',null);
            /*   $records->where(function ($query) {
                $query->where('policies.agent_id', auth()->user()->id)
                    ->orWhere('policies.agent_id','=',null);
            }); */
        }

        if ($request->product_filter != -1 /*&& $request->policyStatus_filter == -1*/) {
            $records->where('policies.product_id', $request->product_filter);
        }

        if($request->FilterBy != -1 ){
            if ($request->FilterBy == 'cellphoneFilter' && $request->value_filter) { // filter Policy by cellphone number, policy_cellphone is the ID of the policy
                $records->where('customer.cellphone', $request->value_filter);
            }
            if ($request->FilterBy == 'referenceFilter' && $request->value_filter ) { // filter Policy by reference number
                $refPolicyNumber = PaymentTransaction::where('referenceNumber',$request->value_filter)->orderBy('id', 'DESC')->pluck( 'policyNumber' )->toArray();
                $records->WhereIn('policies.policyNumber',$refPolicyNumber);
            }
        }

        if ($request->paymentMethod_filter != -1 ) {
            $paymentMethodFilter = PaymentTransaction::where('paymentMethod', $request->paymentMethod_filter)->orderBy('id', 'DESC')->pluck( 'policyNumber' )->toArray();
            $records->WhereIn('policies.policyNumber',$paymentMethodFilter);
        }

        //        if ($request->policy_filter != -1) {
        //            if($request->policy_filter == 1)
        //                $records->where('policies.agent_id', auth()->user()->id);
        //
        //            if($request->policy_filter == 2)
        //                $records->whereNull('policies.agent_id');
        //
        //            if($request->policy_filter == 3)
        //                $records->whereNotNull('policies.agent_id');
        //        }

        if ($request->agent_filter != -1) {
            $records->where('policies.agent_id', $request->agent_filter);
        }
        if (auth::user()->hasPermissionTo('policy-Full List') == false && $searchValue == null) {
            if (auth::user()->hasPermissionTo('policy-Only with Agents') == true)
                $records->whereNotNull('policies.agent_id');

            if (auth::user()->hasPermissionTo('policy-Only with No Agents') == true)
                $records->whereNull('policies.agent_id');

            if (auth::user()->hasPermissionTo('policy-Own Policy') == true)
                $records->where('policies.agent_id', auth()->user()->id);

            if (auth::user()->hasPermissionTo('policy-own and with N/A') == true) {
                $records->where(function ($query) {
                    $query->whereNull('policies.agent_id')
                        ->orWhere('policies.agent_id', auth()->user()->id);
                });
            }
            //$records->where('policies.agent_id',auth()->user()->id)->orWhereNull('policies.agent_id')->orWhere('policies.agent_id','0');
        }

        if ($request->lead_agent != '-1') {
            $records->where('policies.leadAgentID', $request->lead_agent);
        }
        if(auth::user()->hasPermissionTo('T&R') == true){
            $records =  $records;
        }else{
            $records =  $records->where('policies.product_id','!=',6);
        }

        /*$records->where('policies.leadSource', 'like', '%LiveQuote%')
            ->orWhere('policies.leadSource', 'like', '%Graphite%')
            ->orWhere('policies.leadSource', 'like', '%start.alphadirect.co.bw%')
            ->orWhere('policies.leadSource', null);*/

        //        if($searchValue != null){
        //            $totalRecordswithFilter = Policy::join('customer', 'customer.id', 'policies.customer_id')
        //                ->leftJoin('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
        //                ->join('products', 'products.id', 'policies.product_id')
        //                ->leftJoin('vcs_new_transactions', 'vcs_new_transactions.policyNumber', 'policies.policyNumber')
        //                ->where('policies.id', 'like', '%' . $searchValue . '%')
        //                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
        //                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
        //                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
        //                ->orWhere('products.name', 'like', '%' . $searchValue . '%')
        //                /*->orWhere('vcs_new_transactions.reference','like', '%' .$searchValue . '%')*/
        //                ->select('count(*) as allcount')
        //                ->count();
        //        } else {
        //            $totalRecordswithFilter = $records->count();
        //        }

        $totalRecordswithFilter = $records->count();

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get(
                [
                    'policies.id',
                    'policies.customer_id',
                    'policies.product_id',
                    'policies.agent_id',
                    'policies.has_vehicle',
                    'policies.has_member',
                    'policies.policyNumber',
                    'policies.status',
                    'policies.preinspection',
                    'policies.is_bundled',
                    'policies.created_at'
                ]
            );


        $records = json_decode($records, true);

        $data_arr = array();
        $sno = $start + 1;
        foreach ($records as $record) {
            if ($record['id'])
                $id = $record['id'];
            else
                $id = 'N/A';

            if ($record['policyNumber'] && $id != 'N/A') {
                $view = '<a href="' . route('admin.policy.policyView', $id) . '" target="_blank">
                        ' . $record['policyNumber'] . '
                        </a>';
            } else {
                $view = 'N/A';
            }

            if ($request->paymentMethod_filter != -1 ) {
                $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])
                                            ->where('paymentMethod', $request->paymentMethod_filter)
                                            ->orderBy('id', 'DESC')->first();
            }else{
                if ($record['customer_id'] != null) {
                    $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
                }
            }

            if ($trans != null && $trans['referenceNumber'] != null) {
                $referenceNumber = $trans['referenceNumber'];
            } else {
                $referenceNumber = "Payment reference not generated";
            }

            if ($record['customer_id'] != null) {
                $customer = Customer::where('id', $record['customer_id'])->first(array('firstName', 'middleName', 'lastName', 'cellphone','customer_category'));
                if ($customer != null) {
                    if($customer->customer_category == 1){
                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"><span class="graydot"></span>&nbsp&nbsp' . $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName . '</a>';
                    }elseif($customer->customer_category == 2){
                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> <span class="blackdot"></span>&nbsp&nbsp' .$customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName . '</a>';
                    }else{
                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> <span class="greendot"></span>&nbsp&nbsp' .$customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName . '</a>';
                    }
                } else {
                    $name = 'N/A';
                }
            } else {
                $name = 'N/A';
            }

            if ($record['policyNumber'])
                $policyNumber = $record['policyNumber'];
            else
                $policyNumber = 'N/A';

            $cell = isset($customer->cellphone) ? $customer->cellphone : "N/A";

            if ($record['product_id'] != null) {
                $product = Product::where('id', $record['product_id'])->first(array('name'));
                if($record['is_bundled'] == 1){
                    $pName = "Bundled";
                }elseif($product){
                    $pName = $product->name;
                }
            } else {
                $pName = 'N/A';
            }

            $productName = $pName;

            $pAgent = null;
            if ($record['agent_id'] != null) {
                $agent = User::where('id', $record['agent_id'])->first(array('firstName','lastName'));
                if ($agent)
                    $pAgent = ucwords($agent->firstName).'  '.ucwords($agent->lastName);
            } else {
                $pAgent = 'N/A';
            }
            $agentName = $pAgent;

            $payment = '';
            $banking = CustomerBanking::where('policy_id', $record['id'])->first(array('billing'));
            if ($trans && $trans->paymentMethod != null) {
                if ($trans->paymentMethod == 'orangeMoney')
                    $payment = 'Orange USSD';
                else
                    $payment = $trans->paymentMethod;
            } else {
                $data = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                if ($data && $data->referenceNumber)
                    $referenceNumber = $data->referenceNumber;
                else
                    $referenceNumber = 'Reference number not generated';

                if ($data && $data->realPayTransaction_id != null) {
                    $payment = 'RealPay';
                } elseif ($data && $data->vcsTransaction_id != null) {
                    $payment = 'VCS';
                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null) {
                    $payment = 'VCS';
                } elseif ($data && $data->orangeTransaction_id != null && $data->referenceNumber != null) {
                    $payment = 'Orange USSD';
                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null && $data->status == 0) {
                    $payment = 'Payment not initiated';
                } else {
                    if ($banking && $banking->billing)
                        if ($banking->billing == 'orangeMoney')
                            $payment = 'Orange USSD';
                        else
                            $payment = $banking->billing;
                    else
                        $payment = 'Payment method not found';
                }
            }

            $vehicle_plate = Vehicle::where('policy_id', $record['id'])->value('vehiclePlate');
            if ($vehicle_plate == null) {
                $vehicle_plate = 'N/A';
            }
            $kyc = KYC::where('customer_id', $record['customer_id'])->first(array('compliance'));

            $status = '';
            $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
            if ($record['status'] == 1 && $kyc != NULL && $kyc->compliance == 1) {
                $status .= '<span class="kt-font-bold kt-font-brand">Activated</span>';

            } elseif ($record['status'] == 2) {
                $status .= '<span class="kt-font-bold kt-font-danger">Cancel</span>';
            } elseif ($record['status'] == 0) {
                $status .= '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
            } else {
                $status .= '<span class="kt-font-bold kt-font-focus">Deactivated - KYC Pending</span>';
            }

            if( $record['product_id'] == 3){
                $Vehicle1 = Vehicle::where('policy_id', $record['id'])->first();

                    if ($Vehicle1 == null) {
                        $status .='';
                    }else{
                        if ($Vehicle1['status'] == 1) {
                            $status .= '<br><span class="kt-font-bold kt-font-brand">Preinspection Done</span>';
                        }elseif($Vehicle1['status'] == 0){
                            $status .= '<br><span class="kt-font-bold kt-font-focus">Preinspection Pending </span>';
                       }elseif($Vehicle1['status'] == 2){
                        $status .= '<br><span class="kt-font-bold kt-font-danger">Preinspection Unapproved  </span>';
                   }else{
                    $status .= '';
                   }
                    }
                }

            if ($kyc && $kyc->compliance == 1) {
                $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            } elseif ($kyc && $kyc->compliance == 0) {
                $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">KYC Verification Pending </span>';
            } elseif ($kyc && $kyc->compliance == 2) {
                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            } else {
                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Status not found</span>';
            }

            if ($trans != null) {
                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
                } else {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
                }
            } else {
                $trans = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "0") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
                } elseif ($trans != null && $trans['vcsTransaction_id'] == null && $trans['realPayTransaction_id'] == null && $trans['referenceNumber'] != null && strtoupper($trans['status']) == "0") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
                } else {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
                }
            }

            $actions = '';
            if (auth::user()->hasPermissionTo('policy-edit')) {
                $actions .= '<a href="' . route('admin.policy.edit', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            } else {
                $actions .= '<a href="' . route('admin.policy.policyView', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';
            }

            if (auth::user()->hasPermissionTo('policy-archive') && $record['status'] != 1) {
                $actions .= '<a href="#" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-archive" value="'.$id.'" title="Archive">
                                <i class="la la-archive"></i>
                            </a>';
            }

            if (auth::user()->hasPermissionTo('claim-create')) {
                if ($record['status'] == 1 && $kyc && $kyc->compliance == 1) {
                    if ($record['has_vehicle'] == 1 && $record['has_member'] == 1) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass life accident key_loss claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($record['has_vehicle'] == 1 && $record['has_member'] == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass accident key_loss claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($record['has_vehicle'] == 0 && $record['has_member'] == 1) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md life claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($record['product_id'] == 5 && $record['has_vehicle'] == 0 && $record['has_member'] == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md cellphone claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    }elseif ($record['product_id'] == 4 && $record['has_vehicle'] == 0 && $record['has_member'] == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md legal claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    }
                }
            }
            if (auth()->user()->hasRole('Super Admin')) {
                $urlValue = \Config::get('values.graphite_url');
                $stringURLArray = array(env('LOC_ENV'), env('DEV_ENV'), env('QA_ENV'));
                if (in_array($urlValue, $stringURLArray)) {
                    $transaction = $trans = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
                    if ($transaction == null || $transaction['status'] != 'SUCCESS') {
                        $actions .= '<button value="' . $id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md change_payment_status" title="Change Payment Status">
                                <i class="la la-caret-up"></i>
                                </button>';
                    }
                }
            }

            $data_arr[] = array(
                "id"              => $id,
                "view"            => $view,
                "referenceNumber" => $referenceNumber,
                "name"            => $name,
                "cellphone"       => $cell,
                "product_name"    => $productName,
                "agentName"       => $agentName,
                "status"          => $status,
                "payment_method"  => $payment,
                "vehicle_plate"   => ucfirst($vehicle_plate),
                "created_at"      => Carbon::parse($record['created_at'])->format('d-m-Y H:i'),
                "actions"         => $actions

            );
        }

        $policies = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        echo json_encode($policies);

        exit;
    }

    public function linkedPolicyData(Request $request)
    {
        // $request->linkedCustomerId;
        ## Read value
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');
        $pName = null;
        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = trim($search_arr['value']); // Search value

        // Total records
        $totalRecords = Policy::select('count(*) as allcount')->count();
        # DB::enableQueryLog();
        // Fetch records
        $records = Policy::orderBy('id', 'DESC')
            ->leftJoin('customer', 'customer.id', 'policies.customer_id')
            ->leftJoin('products', 'products.id', 'policies.product_id')
            ->leftJoin('vehicle', 'vehicle.policy_id', 'policies.id');

        if ($searchValue != null) {
            $records->where('policies.id', 'like', '%' . $searchValue . '%')
                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
                ->orWhere('vehicle.vehiclePlate', 'like', '%' . $searchValue . '%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'), 'like', '%' . $searchValue . '%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'), 'like', '%' . $searchValue . '%');
            #->orWhere('customer.cellphone', 'like', '%' .$searchValue . '%');
        }

        if ($request->policyStatus_filter != -1 /*&& $request->product_filter == -1*/) {
            $records->where('policies.status', $request->policyStatus_filter);
        }

        if (!empty($request->linkedCustomerId)) {
            $records->where('policies.customer_id', $request->linkedCustomerId);
        }

        if (!empty($request->linkedPolicyId)) {
            $records->where('policies.id','!=', $request->linkedPolicyId);
        }

        if ((Auth::user()->hasRole('Manager') || Auth::user()->hasRole('Super Admin')) == false) {
            # $records->where('policies.agent_id',auth()->user()->id)->orWhere('policies.agent_id','=',null);
            /*   $records->where(function ($query) {
                $query->where('policies.agent_id', auth()->user()->id)
                    ->orWhere('policies.agent_id','=',null);
            }); */
        }

        if ($request->product_filter != -1 /*&& $request->policyStatus_filter == -1*/) {
            $records->where('policies.product_id', $request->product_filter);
        }

        if ($request->cellphone_filter) { // filter Policy by cellphone number, policy_cellphone is the ID of the policy
            $records->where('customer.cellphone', 'like', '%' . $request->cellphone_filter . '%');
        }
        /*if ($request->reference_filter) { // filter Policy by reference number
            $records->where('vcs_new_transactions.reference','like', '%' . $request->reference_filter  . '%');
        }*/

        //        if ($request->policy_filter != -1) {
        //            if($request->policy_filter == 1)
        //                $records->where('policies.agent_id', auth()->user()->id);
        //
        //            if($request->policy_filter == 2)
        //                $records->whereNull('policies.agent_id');
        //
        //            if($request->policy_filter == 3)
        //                $records->whereNotNull('policies.agent_id');
        //        }
        if ($request->agent_filter != -1) {
            $records->where('policies.agent_id', $request->agent_filter);
        }
        if (auth::user()->hasPermissionTo('policy-Full List') == false && $searchValue == null) {
            if (auth::user()->hasPermissionTo('policy-Only with Agents') == true)
                $records->whereNotNull('policies.agent_id');

            if (auth::user()->hasPermissionTo('policy-Only with No Agents') == true)
                $records->whereNull('policies.agent_id');

            if (auth::user()->hasPermissionTo('policy-Own Policy') == true)
                $records->where('policies.agent_id', auth()->user()->id);

            if (auth::user()->hasPermissionTo('policy-own and with N/A') == true) {
                $records->where(function ($query) {
                    $query->whereNull('policies.agent_id')
                        ->orWhere('policies.agent_id', auth()->user()->id);
                });
            }
            //$records->where('policies.agent_id',auth()->user()->id)->orWhereNull('policies.agent_id')->orWhere('policies.agent_id','0');
        }

        if ($request->lead_agent != '-1') {
            $records->where('policies.leadAgentID', $request->lead_agent);
        }
        if(auth::user()->hasPermissionTo('T&R') == true){
            $records =  $records;
        }else{
            $records =  $records->where('policies.product_id','!=',6);
        }

        /*$records->where('policies.leadSource', 'like', '%LiveQuote%')
            ->orWhere('policies.leadSource', 'like', '%Graphite%')
            ->orWhere('policies.leadSource', 'like', '%start.alphadirect.co.bw%')
            ->orWhere('policies.leadSource', null);*/

        //        if($searchValue != null){
        //            $totalRecordswithFilter = Policy::join('customer', 'customer.id', 'policies.customer_id')
        //                ->leftJoin('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
        //                ->join('products', 'products.id', 'policies.product_id')
        //                ->leftJoin('vcs_new_transactions', 'vcs_new_transactions.policyNumber', 'policies.policyNumber')
        //                ->where('policies.id', 'like', '%' . $searchValue . '%')
        //                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
        //                ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
        //                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
        //                ->orWhere('products.name', 'like', '%' . $searchValue . '%')
        //                /*->orWhere('vcs_new_transactions.reference','like', '%' .$searchValue . '%')*/
        //                ->select('count(*) as allcount')
        //                ->count();
        //        } else {
        //            $totalRecordswithFilter = $records->count();
        //        }

        $totalRecordswithFilter = $records->count();

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get(
                [
                    'policies.id',
                    'policies.customer_id',
                    'policies.product_id',
                    'policies.agent_id',
                    'policies.has_vehicle',
                    'policies.has_member',
                    'policies.policyNumber',
                    'policies.status',
                    'policies.preinspection',
                    'policies.is_bundled',
                    'policies.created_at'
                ]
            );


        $records = json_decode($records, true);

        $data_arr = array();
        $sno = $start + 1;
        foreach ($records as $record) {
            if ($record['id'])
                $id = $record['id'];
            else
                $id = 'N/A';

            if ($record['policyNumber'] && $id != 'N/A') {
                $view = '<a href="' . route('admin.policy.policyView', $id) . '" target="_blank">
                        ' . $record['policyNumber'] . '
                        </a>';
            } else {
                $view = 'N/A';
            }

            if ($record['customer_id'] != null) {
                $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
            }

            if ($trans != null && $trans['referenceNumber'] != null) {
                $referenceNumber = $trans['referenceNumber'];
            } else {
                $referenceNumber = "Payment reference not generated";
            }

            if ($record['customer_id'] != null) {
                $customer = Customer::where('id', $record['customer_id'])->first(array('firstName', 'middleName', 'lastName', 'cellphone','customer_category'));
                if ($customer != null) {
                    if($customer->customer_category == 1){
                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"><span class="graydot"></span>&nbsp&nbsp' . $customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName . '</a>';
                    }elseif($customer->customer_category == 2){
                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> <span class="blackdot"></span>&nbsp&nbsp' .$customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName . '</a>';
                    }else{
                        $name = '<a href="' . route('admin.customer.edit', $record['customer_id']) . '" target="_blank"> <span class="greendot"></span>&nbsp&nbsp' .$customer->firstName . ' ' . $customer->middleName . ' ' . $customer->lastName . '</a>';
                    }
                } else {
                    $name = 'N/A';
                }
            } else {
                $name = 'N/A';
            }

            if ($record['policyNumber'])
                $policyNumber = $record['policyNumber'];
            else
                $policyNumber = 'N/A';

            $cell = isset($customer->cellphone) ? $customer->cellphone : "N/A";

            if ($record['product_id'] != null) {
                $product = Product::where('id', $record['product_id'])->first(array('name'));
                if($record['is_bundled'] == 1){
                    $pName = "Bundled";
                }elseif($product){
                    $pName = $product->name;
                }
            } else {
                $pName = 'N/A';
            }

            $productName = $pName;

            $pAgent = null;
            if ($record['agent_id'] != null) {
                $agent = User::where('id', $record['agent_id'])->first(array('firstName','lastName'));
                if ($agent)
                    $pAgent = ucwords($agent->firstName).'  '.ucwords($agent->lastName);
            } else {
                $pAgent = 'N/A';
            }
            $agentName = $pAgent;

            $payment = '';
            $banking = CustomerBanking::where('policy_id', $record['id'])->first(array('billing'));
            if ($trans && $trans->paymentMethod != null) {
                if ($trans->paymentMethod == 'orangeMoney')
                    $payment = 'Orange USSD';
                else
                    $payment = $trans->paymentMethod;
            } else {
                $data = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                if ($data && $data->referenceNumber)
                    $referenceNumber = $data->referenceNumber;
                else
                    $referenceNumber = 'Reference number not generated';

                if ($data && $data->realPayTransaction_id != null) {
                    $payment = 'RealPay';
                } elseif ($data && $data->vcsTransaction_id != null) {
                    $payment = 'VCS';
                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null) {
                    $payment = 'VCS';
                } elseif ($data && $data->orangeTransaction_id != null && $data->referenceNumber != null) {
                    $payment = 'Orange USSD';
                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null && $data->status == 0) {
                    $payment = 'Payment not initiated';
                } else {
                    if ($banking && $banking->billing)
                        if ($banking->billing == 'orangeMoney')
                            $payment = 'Orange USSD';
                        else
                            $payment = $banking->billing;
                    else
                        $payment = 'Payment method not found';
                }
            }

            $vehicle_plate = Vehicle::where('policy_id', $record['id'])->value('vehiclePlate');
            if ($vehicle_plate == null) {
                $vehicle_plate = 'N/A';
            }
            $kyc = KYC::where('customer_id', $record['customer_id'])->first(array('compliance'));

            $status = '';
            $trans = PaymentTransaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
            if ($record['status'] == 1 && $kyc != NULL && $kyc->compliance == 1) {
                $status .= '<span class="kt-font-bold kt-font-brand">Activated</span>';

            } elseif ($record['status'] == 2) {
                $status .= '<span class="kt-font-bold kt-font-danger">Cancel</span>';
            } elseif ($record['status'] == 0) {
                $status .= '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
            } else {
                $status .= '<span class="kt-font-bold kt-font-focus">Deactivated - KYC Pending</span>';
            }

            if( $record['product_id'] == 3){
                $Vehicle1 = Vehicle::where('policy_id', $record['id'])->first();

                    if ($Vehicle1 == null) {
                        $status .='';
                    }else{
                        if ($Vehicle1['status'] == 1) {
                            $status .= '<br><span class="kt-font-bold kt-font-brand">Preinspection Done</span>';
                        }elseif($Vehicle1['status'] == 0){
                            $status .= '<br><span class="kt-font-bold kt-font-focus">Preinspection Pending </span>';
                       }elseif($Vehicle1['status'] == 2){
                        $status .= '<br><span class="kt-font-bold kt-font-danger">Preinspection Unapproved  </span>';
                   }else{
                    $status .= '';
                   }
                    }
                }

            if ($kyc && $kyc->compliance == 1) {
                $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            } elseif ($kyc && $kyc->compliance == 0) {
                $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">KYC Verification Pending </span>';
            } elseif ($kyc && $kyc->compliance == 2) {
                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            } else {
                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Status not found</span>';
            }

            if ($trans != null) {
                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
                } else {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
                }
            } else {
                $trans = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "0") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
                } elseif ($trans != null && $trans['vcsTransaction_id'] == null && $trans['realPayTransaction_id'] == null && $trans['referenceNumber'] != null && strtoupper($trans['status']) == "0") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
                } else {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
                }
            }

            $actions = '';
            if (auth::user()->hasPermissionTo('policy-edit')) {
                $actions .= '<a href="' . route('admin.policy.edit', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            } else {
                $actions .= '<a href="' . route('admin.policy.policyView', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';
            }

            if (auth::user()->hasPermissionTo('policy-archive') && $record['status'] != 1) {
                $actions .= '<a href="#" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-archive" value="'.$id.'" title="Archive">
                                <i class="la la-archive"></i>
                            </a>';
            }

            if (auth::user()->hasPermissionTo('claim-create')) {
                if ($record['status'] == 1 && $kyc && $kyc->compliance == 1) {
                    if ($record['has_vehicle'] == 1 && $record['has_member'] == 1) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass life accident key_loss claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($record['has_vehicle'] == 1 && $record['has_member'] == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass accident key_loss claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($record['has_vehicle'] == 0 && $record['has_member'] == 1) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md life claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($record['product_id'] == 5 && $record['has_vehicle'] == 0 && $record['has_member'] == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md cellphone claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    }elseif ($record['product_id'] == 4 && $record['has_vehicle'] == 0 && $record['has_member'] == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md legal claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    }
                }
            }
            if (auth()->user()->hasRole('Super Admin')) {
                $urlValue = \Config::get('values.graphite_url');
                $stringURLArray = array(env('LOC_ENV'), env('DEV_ENV'), env('QA_ENV'));
                if (in_array($urlValue, $stringURLArray)) {
                    $transaction = $trans = Transaction::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
                    if ($transaction == null || $transaction['status'] != 'SUCCESS') {
                        $actions .= '<button value="' . $id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md change_payment_status" title="Change Payment Status">
                                <i class="la la-caret-up"></i>
                                </button>';
                    }
                }
            }

            $data_arr[] = array(
                "id"              => $id,
                "view"            => $view,
                "referenceNumber" => $referenceNumber,
                "name"            => $name,
                "cellphone"       => $cell,
                "product_name"    => $productName,
                "agentName"       => $agentName,
                "status"          => $status,
                "payment_method"  => $payment,
                "vehicle_plate"   => ucfirst($vehicle_plate),
                "created_at"      => Carbon::parse($record['created_at'])->format('d-m-Y H:i'),
                "actions"         => $actions

            );
        }

        $policies = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        echo json_encode($policies);

        exit;
    }

    public function confirmArchive(Request $request){
        $policy = Policy::where('id',$request->get('id'))->first(array('policyNumber'));
        $body = 'Are you sure you want to archive policy #'. $policy->policyNumber .' ? You can not revert this process.';
        return response()->json(['status'=>'success', 'id'=>$request->get('id'), 'body'=>$body]);
   }

    public function archivedData(Request $request)
    {
        ## Read value
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = trim($search_arr['value']); // Search value

        // Total records
        $totalRecords = ArchivedPolicies::select('count(*) as allcount')->count();
        # DB::enableQueryLog();
        // Fetch records
        $records = ArchivedPolicies::orderBy('id', 'DESC')
            ->leftJoin('archived_customer', 'archived_customer.id', 'archived_policies.customer_id')
            ->leftJoin('products', 'products.id', 'archived_policies.product_id');

        if ($searchValue != null) {
            $records->where('archived_policies.id', 'like', '%' . $searchValue . '%')
                ->orWhere('archived_policies.policyNumber', 'like', '%' . $searchValue . '%')
                ->orWhere('archived_customer.firstName', 'like', '%' . $searchValue . '%')
                ->orWhere('archived_customer.lastName', 'like', '%' . $searchValue . '%');
            #->orWhere('customer.cellphone', 'like', '%' .$searchValue . '%');
        }

        if ($request->policyStatus_filter != -1 /*&& $request->product_filter == -1*/) {
            $records->where('archived_policies.status', $request->policyStatus_filter);
        }

        if ((Auth::user()->hasRole('Manager') || Auth::user()->hasRole('Super Admin')) == false) {
            # $records->where('policies.agent_id',auth()->user()->id)->orWhere('policies.agent_id','=',null);
            /*   $records->where(function ($query) {
                  $query->where('policies.agent_id', auth()->user()->id)
                      ->orWhere('policies.agent_id','=',null);
              }); */
        }

        if ($request->product_filter != -1 /*&& $request->policyStatus_filter == -1*/) {
            $records->where('archived_policies.product_id', $request->product_filter);
        }

        if ($request->cellphone_filter) { // filter Policy by cellphone number, policy_cellphone is the ID of the policy
            $records->where('archived_customer.cellphone', 'like', '%' . $request->cellphone_filter . '%');
        }
        /*if ($request->reference_filter) { // filter Policy by reference number
            $records->where('vcs_new_transactions.reference','like', '%' . $request->reference_filter  . '%');
        }*/

        //        if ($request->policy_filter != -1) {
        //            if($request->policy_filter == 1)
        //                $records->where('policies.agent_id', auth()->user()->id);
        //
        //            if($request->policy_filter == 2)
        //                $records->whereNull('policies.agent_id');
        //
        //            if($request->policy_filter == 3)
        //                $records->whereNotNull('policies.agent_id');
        //        }
        if ($request->agent_filter != -1) {
            $records->where('archived_policies.agent_id', $request->agent_filter);
        }
        if (auth::user()->hasPermissionTo('policy-Full List') == false && $searchValue == null) {
            if (auth::user()->hasPermissionTo('policy-Only with Agents') == true)
                $records->whereNotNull('archived_policies.agent_id');

            if (auth::user()->hasPermissionTo('policy-Only with No Agents') == true)
                $records->whereNull('archived_policies.agent_id');

            if (auth::user()->hasPermissionTo('policy-Own Policy') == true)
                $records->where('archived_policies.agent_id', auth()->user()->id);

            if (auth::user()->hasPermissionTo('policy-own and with N/A') == true) {
                $records->where(function ($query) {
                    $query->whereNull('archived_policies.agent_id')
                        ->orWhere('archived_policies.agent_id', auth()->user()->id);
                });
            }




            //$records->where('policies.agent_id',auth()->user()->id)->orWhereNull('policies.agent_id')->orWhere('policies.agent_id','0');
        }

        if ($request->lead_agent != '-1') {
            $records->where('archived_policies.leadAgentID', $request->lead_agent);
        }
        /*$records->where('policies.leadSource', 'like', '%LiveQuote%')
            ->orWhere('policies.leadSource', 'like', '%Graphite%')
            ->orWhere('policies.leadSource', 'like', '%start.alphadirect.co.bw%')
            ->orWhere('policies.leadSource', null);*/

        //        if($searchValue != null){
        //            $totalRecordswithFilter = Policy::join('customer', 'customer.id', 'policies.customer_id')
        //                ->leftJoin('customer_kyc', 'customer_kyc.customer_id', 'policies.customer_id')
        //                ->join('products', 'products.id', 'policies.product_id')
        //                ->leftJoin('vcs_new_transactions', 'vcs_new_transactions.policyNumber', 'policies.policyNumber')
        //                ->where('policies.id', 'like', '%' . $searchValue . '%')
        //                ->orWhere('policies.policyNumber', 'like', '%' . $searchValue . '%')
        //                ->orWhere('   customer.firstName', 'like', '%' . $searchValue . '%')
        //                ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
        //                ->orWhere('products.name', 'like', '%' . $searchValue . '%')
        //                /*->orWhere('vcs_new_transactions.reference','like', '%' .$searchValue . '%')*/
        //                ->select('count(*) as allcount')
        //                ->count();
        //        } else {
        //            $totalRecordswithFilter = $records->count();
        //        }

        $totalRecordswithFilter = $records->count();

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get(
                [
                    'archived_policies.id',
                    'archived_policies.archived_by',
                    'archived_policies.customer_id',
                    'archived_policies.product_id',
                    'archived_policies.has_vehicle',
                    'archived_policies.has_member',
                    'archived_policies.policyNumber',
                    'archived_policies.status',
                    'archived_policies.created_at'
                ]
            );
        # dd(DB::getQueryLog());
        $records = json_decode($records, true);

        $data_arr = array();
        $sno = $start + 1;
        foreach ($records as $record) {
            if ($record['id'])
                $id = $record['id'];
            else
                $id = 'N/A';

            if ($record['policyNumber'] && $id != 'N/A') {
                $view = $record['policyNumber'];
            } else {
                $view = 'N/A';
            }


            if ($record['customer_id'] != null) {
                $trans = ArchivedPaymentTransactions::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
            }

            if ($trans != null && $trans['referenceNumber'] != null) {
                $referenceNumber = $trans['referenceNumber'];
            } else {
                $referenceNumber = "Payment reference not generated";
            }

            if ($record['customer_id'] != null) {
                $customer = Archived_customer::where('id', $record['customer_id'])->first(array('firstName', 'lastName', 'cellphone'));
                if ($customer != null) {
                    $cname = ucwords($customer->firstName) . ' ' . ucwords($customer->lastName);
                } else {
                    $cname = 'N/A';
                }
            } else {
                $cname = 'N/A';
            }


            if ($record['policyNumber'])
                $policyNumber = $record['policyNumber'];
            else
                $policyNumber = 'N/A';

            $cell = isset($customer->cellphone) ? $customer->cellphone : "N/A";

            if ($record['product_id'] != null) {
                $product = Product::where('id', $record['product_id'])->first(array('name'));
                if ($product)
                    $pName = $product->name;
            } else {
                $pName = 'N/A';
            }

            $user = User::where('id', $record['archived_by'])->first(array('firstName', 'lastName'));

            if ($user != null) {
                $name = $user->firstName . ' ' . $user->lastName;
            } else {
                $name = '-';
            }

            $productName = $pName;

            $status = '';
            $trans = ArchivedPaymentTransactions::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first();
            if ($record['status'] == 1) {
                $status .= '<span class="kt-font-bold kt-font-brand">Activated</span>';
            } elseif ($record['status'] == 2) {
                $status .= '<span class="kt-font-bold kt-font-danger">Cancel</span>';
            } else {
                $status .= '<span class="kt-font-bold kt-font-focus">Deactivated</span>';
            }

            $payment = '';
            $banking = ArchivedCustomerBanking::where('policy_id', $record['id'])->first(array('billing'));
            if ($trans && $trans->paymentMethod != null) {
                if ($trans->paymentMethod == 'orangeMoney')
                    $payment = 'Orange USSD';
                else
                    $payment = $trans->paymentMethod;
            } else {
                $data = ArchivedTransactions::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                if ($data && $data->referenceNumber)
                    $referenceNumber = $data->referenceNumber;
                else
                    $referenceNumber = 'Reference number not generated';

                if ($data && $data->realPayTransaction_id != null) {
                    $payment = 'RealPay';
                } elseif ($data && $data->vcsTransaction_id != null) {
                    $payment = 'VCS';
                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null) {
                    $payment = 'VCS';
                } elseif ($data && $data->orangeTransaction_id != null && $data->referenceNumber != null) {
                    $payment = 'Orange USSD';
                } elseif ($data && $data->realPayTransaction_id == null && $data->referenceNumber != null && $data->status == 0) {
                    $payment = 'Payment not initiated';
                } else {
                    if ($banking && $banking->billing)
                        $payment = $banking->billing;
                    else
                        $payment = 'Payment method not found';
                }
            }

            $kyc = ArchivedKYC::where('customer_id', $record['customer_id'])->first();

            if ($kyc && $kyc->compliance == 1) {
                $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            } elseif ($kyc && $kyc->compliance == 0) {
                $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">KYC Verification Pending </span>';
            } elseif ($kyc && $kyc->compliance == 2) {
                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            } else {
                $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Status not found</span>';
            }

            $actions = '<a href="' . route('admin.policy.restore', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Archive">
                                <i class="la la-archive"></i>
                            </a>';

            if ($trans != null) {
                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
                } else {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
                }
            } else {
                $trans = ArchivedTransactions::where('policyNumber', $record['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                    $status .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Payment Successful</span>';
                } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Pending</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                    $status .= '<br><span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill">Payment Processing</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Cancelled</span>';
                } elseif ($trans != null && strtoupper($trans['status']) == "0") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
                } elseif ($trans != null && $trans['vcsTransaction_id'] == null && $trans['realPayTransaction_id'] == null && $trans['referenceNumber'] != null && strtoupper($trans['status']) == "0") {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment not initiated</span>';
                } else {
                    $status .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">Payment Unsuccessful</span>';
                }
            }



            $data_arr[] = array(
                "id" => $id,
                "view" => $view,
                "referenceNumber" => $referenceNumber,
                "name" => $cname,
                "cellphone" => $cell,
                "product_name" => $productName,
                "status" => $status,
                "payment_method" => $payment,
                "created_at" => $record['created_at'],
                "archived_by" => ucfirst($name),
                "actions" => $actions,

            );
        }

        $policies = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        echo json_encode($policies);

        exit;
    }
    public function archivePolicyData($id)
    {
        $toArchive = Policy::where('id', $id)->where('status', '!=', 1)->first();


        try {
       //     \Illuminate\Support\Facades\DB::beginTransaction();
         //   DB::connection()->enableQueryLog();
            $toArchive = Policy::where('id', $id)->where('status', '!=', 1)->first();

            //Archive policy data
            if ($toArchive != null) {
                $policy = $toArchive->toArray();
                $archived = new ArchivedPolicies();
                foreach ($policy as $key => $data) {
                    $archived->$key = $data;
                }
                $archived->archived_by = auth::user()->id;
                if ($archived->save()){
                     $toArchive->delete();
                }
              //  $queries = DB::getQueryLog();
               // dd($queries);

                //Check if the customer holds multiple policies
                $checkCount = Policy::where('customer_id', $toArchive->customer_id)
                    ->where('id', '!=', $toArchive->id)->where('status', '!=', 1)
                    ->get(array('id', 'customer_id'))
                    ->count();

                //Archive customer data
                $ar_check_customer = Archived_customer::where('id', $toArchive->customer_id)->get(array('id'))->count();
                $ar_check_customer_profile = ArchivedCustomerProfile::where('customer_id', $toArchive->customer_id)->get(array('id'))->count();
                if ($ar_check_customer == 0 && $ar_check_customer_profile == 0) {
                    if ($toArchive->customer_id != null) {
                        $ar_customer = Customer::where('id', $toArchive->customer_id)->first();
                        if ($ar_customer != null) {
                            $customer = $ar_customer->toArray();
                            $ard_customer = new Archived_customer();
                            foreach ($customer as $key => $cdata) {
                                $ard_customer->$key = $cdata;
                            }
                            $ard_customer->save();

                            if ($ard_customer->save() && $checkCount == 0)
                                $ar_customer->delete();

                            $profile = CustomerProfile::where('customer_id', $toArchive->customer_id)->first();
                            if ($profile != null) {
                                $profile_arr = $profile->toArray();
                                $ar_profile = new ArchivedCustomerProfile();
                                foreach ($profile_arr as $key => $pdata) {
                                    $ar_profile->$key = $pdata;
                                }
                                $ar_profile->save();
                                if ($ar_profile->save() && $checkCount == 0)
                                    $profile->delete();
                            }
                        }
                    }
                }


                //Archive Customer KYC Data
                $kyc = KYC::where('customer_id', $toArchive->customer_id)->first();
                if ($kyc != null) {
                    $ar_kyc_check = ArchivedKYC::where('customer_id', $toArchive->customer_id)->get(array('id'))->count();
                    if ($ar_kyc_check == 0) {
                        $kyc_arr = $kyc->toArray();
                        $ar_kyc = new ArchivedKYC();
                        foreach ($kyc_arr as $key => $kycdata) {
                            $ar_kyc->$key = $kycdata;
                        }
                        $ar_kyc->save();

                        if ($ar_kyc->save() && $checkCount == 0)
                            $kyc->delete();
                    }
                }


                //Archive Vehicle data
                if ($toArchive->has_vehicle == 1) {
                    $vehicle = Vehicle::where('policy_id', $toArchive->id)->first();
                    if ($vehicle != null) {
                        $ar_vehicle_check = ArchivedVehicle::where('policy_id', $toArchive->id)->get(array('id'))->count();
                        if ($ar_vehicle_check == 0) {
                            $vehicle_arr = $vehicle->toArray();
                            $ar_vehicle = new ArchivedVehicle();
                            foreach ($vehicle_arr as $key => $vdata) {
                                $ar_vehicle->$key = $vdata;
                            }
                            $ar_vehicle->save();

                            if ($ar_vehicle->save())
                                $vehicle->delete();
                        }
                    }
//                    else {
//                        \Illuminate\Support\Facades\DB::rollBack();
//                        return Redirect::back()->with('error', 'Vehicle details not found , you can not archive');
//                    }
                }

                //Archive Policy Beneficiaries
                if ($toArchive->has_member == 1) {
                    $benef = PolicyBeneficiary::where('policy_id', $toArchive->id)->get();
                    if ($benef->count() != 0) {
                        foreach ($benef as $key => $b) {
                            $ar_policy_benef_check = ArchivedPolicyBeneficiary::where('id', $b->id)->get(array('id'));
                            if ($ar_policy_benef_check->count() == 0) {
                                $b_arr = $b->toArray();
                                $add_ar_benef = new ArchivedPolicyBeneficiary();
                                foreach ($b_arr as $key => $details) {
                                    $add_ar_benef->$key = $details;
                                }
                                $add_ar_benef->save();

                                if ($add_ar_benef->save())
                                    PolicyBeneficiary::where('id', $b->id)->delete();
                            }
                        }
                    }
                }

                //Archive Customer Banking Details
                $banking = CustomerBanking::where('policy_id', $toArchive->id)->first();
                if ($banking != null) {
                    $ar_banking_check = ArchivedCustomerBanking::where('policy_id', $toArchive->id)->get(array('id'))->count();
                    if ($ar_banking_check == 0) {
                        $banking_arr = $banking->toArray();
                        $ar_banking = new ArchivedCustomerBanking();
                        foreach ($banking_arr as $key => $bdata) {
                            $ar_banking->$key = $bdata;
                        }
                        $ar_banking->save();

                        if ($ar_banking->save())
                            $banking->delete();
                    }
                }

                //Archive Cellphone Policies
                if ($toArchive->product_id == 5) {
                    $cellphone = PolicyCellPhone::where('policy_id', $toArchive->id)->get();
                    if ($cellphone->count() != 0) {
                        foreach ($cellphone as $key => $cell) {
                            $ar_cellphone = ArchivedPolicyCellphone::where('id', $cell->id)->first();
                            if ($ar_cellphone == null) {
                                $cellp = new ArchivedPolicyCellphone();
                                $cell_arr = $cell->toArray();
                                foreach ($cell_arr as $key => $cp) {
                                    $cellp->$key = $cp;
                                }
                                $cellp->save();

                                if ($cellp->save())
                                    $cellphone = PolicyCellPhone::where('id', $cell->id)->delete();
                            } else {
                                $ar_cellphone->delete();
                            }
                        }
                    }
                }

                //Check for Transactions
                $tranactions = Transaction::where('policyNumber', $toArchive->policyNumber)->get();
                if ($tranactions->count() != 0) {
                    foreach ($tranactions as $key => $tranaction) {
                        $trans = $tranaction->toArray();
                        $ar_trans = new ArchivedTransactions();
                        foreach ($trans as $key => $t) {
                            $ar_trans->$key = $t;
                        }
                        $ar_trans->save();

                        Transaction::where('id', $tranaction->id)->delete();
                    }
                }

                //Check for Payment Transactions
                $paymentTrans = PaymentTransaction::where('policyNumber', $toArchive->policyNumber)->get();
                if ($paymentTrans->count() != 0) {
                    foreach ($paymentTrans as $key => $ptr) {
                        $ar_payTrans = new ArchivedPaymentTransactions();
                        $arr_ptr = $ptr->toArray();
                        foreach ($arr_ptr as $key => $pt) {
                            $ar_payTrans->$key = $pt;
                        }
                        $ar_payTrans->save();
                        PaymentTransaction::destroy($ptr->id);
                    }
                }

                //check VCS Transactions
                $vcsTrans = VcsNewTransaction::where('policyNumber', $toArchive->policyNumber)->get();
                if ($vcsTrans->count() != 0) {
                    foreach ($vcsTrans as $key => $tr) {
                        $arch_tran = new ArchivedVCSNewTransaction();
                        $arr_tr = $tr->toArray();
                        foreach ($arr_tr as $key => $tra) {
                            $arch_tran->$key = $tra;
                        }
                        $arch_tran->save();

                        if ($arch_tran->save() == true) {
                            VcsNewTransaction::destroy($tr->id);
                        }
                    }
                }

                //Check with Realpay transactions
                $realpays = RealpayLogs::where('policy_id', $toArchive->id)->get();
                if ($realpays->count() != 0) {
                    foreach ($realpays as $key => $log) {
                        $arr_log = $log->toArray();
                        $arv_log = new ArchivedRealpayLogs();
                        foreach ($arr_log as $key => $ar_log) {
                            $arv_log->$key = $ar_log;
                        }
                        $arv_log->save();

                        if ($arv_log->save() == true) {
                            RealpayLogs::destroy($log->id);
                        }
                    }
                }

                $paymentReq = RealpayPaymentRequest::where('policy_id', $toArchive->id)->get();
                if ($paymentReq->count() != 0) {
                    foreach ($paymentReq as $key => $req) {
                        $arr_req = $req->toArray();
                        $arv_req = new ArchivedRealpayPaymentRequest();
                        foreach ($arr_req as $key => $ar_req) {
                            $arv_req->$key = $ar_req;
                        }
                        $arv_req->save();

                        if ($arv_req->save() == true) {
                            RealpayPaymentRequest::destroy($req->id);
                        }
                    }
                }

                $realpayInstalments = RealpayContractInstallments::where('clientNumber', $toArchive->policyNumber)->get();
                foreach ($realpayInstalments as $key => $ins) {
                    RealpayContractInstallments::destroy($ins->id);
                }

                //Check other policies with the same customer
                $check = Policy::where('customer_id', $toArchive->customer_id)->where('status', '!=', 1)->get(array('id', 'customer_id'));

                if ($check->count() != 0) {
                    foreach ($check as $key => $d) {
                        if ($d->id != null && $d->status != 1)
                            $this->archivePolicyData($d->id);
                    }
                }

             //   \Illuminate\Support\Facades\DB::commit();
                return Redirect::back()->with('success', 'Data successfully archived for the policy');
            }
            //            else{
            //                \Illuminate\Support\Facades\DB::rollBack();
            //                return Redirect::back()->with('error', 'Policy is active , you can not archive');
            //            }
        } catch (\Exception $e) {

          //  \Illuminate\Support\Facades\DB::rollBack();
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    public function restorePolicyData($id){
        try {
            dd($id);
        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function sendSMSData(Request $request)
    {
        try {
            $and = false;
            $sms_template_id = $request->sms_template_id;
            if ($sms_template_id && $sms_template_id != 'all') {
                $whrClouse = 'where sms_template_id = ' . $sms_template_id;
            } else {
                $whrClouse = '';
            }
            if ($request->policyStatus_filter[0] == 'all' || $request->policyStatus_filter == 'all') {
                $policyStatus = '';
            } else {
                $policyStatus = ' p.status IN ("' . implode(',', $request->policyStatus_filter) . '") ';
            }
            if ($request->product_filter[0] == 'all' || $request->product_filter == 'all') {
                $productStatus = '';
            } else {
                $productStatus = ' prd.id IN ("' . implode(',', $request->product_filter) . '") ';
            }
            if ($request->transaction_filter == 'all') {
                $transStatus = '';
            } else {
                switch ($request->transaction_filter) {
                    case '0':
                        $transStatus = ' t.status = "SUCCESS"';
                        break;

                    case '1':
                        $transStatus = ' t.status = "FAILED"  and t.policyNumber not IN (select transactions.policyNumber from transactions where transactions.status = "SUCCESS")';
                        break;

                    case '2':
                        $transStatus = ' t.status = "0" and t.policyNumber not IN (select transactions.policyNumber from transactions where transactions.status = "SUCCESS")';
                        break;

                    case '4':
                        $transStatus = ' t.status IN ("0","FAILED") and t.policyNumber not IN (select transactions.policyNumber from transactions where transactions.status = "SUCCESS")';
                        break;

                    default:
                        $transStatus = ' t.status IN ("0","FAILED","SUCCESS")';
                        break;
                }
            }
            if ($request->policySmsFilter != 'select' && $request->before_date != '' && $request->after_date != '') {
                switch ($request->policySmsFilter) {
                    case '0':
                        $filterByPolicySMS = '  (p.created_at BETWEEN "' . $request->before_date . '" AND  "' . $request->after_date . '") ';
                        break;

                    case '1':
                        $filterByPolicySMS = '  (sms_created_at BETWEEN "' . $request->before_date . '" AND  "' . $request->after_date . '") ';
                        break;

                    default:
                        $filterByPolicySMS = '';
                        break;
                }
            } else {
                $filterByPolicySMS = '';
            }

            if ($request->KycStatus != 'select') {
                switch ($request->KycStatus) {
                    case '0':
                        $filterByCustomerKyc = '  ck.compliance = 0 ';
                        break;

                    case '1':
                        $filterByCustomerKyc = '  ck.compliance = 1 ';
                        break;

                    default:
                        $filterByCustomerKyc = '';
                        break;
                }
            } else {
                $filterByCustomerKyc = '';
            }
            $query = 'SELECT  distinct p.policyNumber,p.id,p.created_at as policyCreatedDate, c.firstName,c.lastName,sms_created_at,total,
        c.cellphone,prd.name as prdName,pl.name as planName,p.premium,ck.compliance
        From policies p
        LEFT JOIN transactions t  ON p.policyNumber = t.policyNumber
        INNER JOIN products prd  ON p.product_id = prd.id
        INNER JOIN product_plans pl ON p.plan_id = pl.id
        INNER JOIN customer c ON p.customer_id = c.id
        LEFT JOIN customer_kyc ck ON p.customer_id = ck.customer_id
        Left outer JOIN (select policyNumber,max(created_at) as sms_created_at,count(*) as total  from sms_logs ' . $whrClouse . '   group by policyNumber order by id desc) sms_logs
        ON sms_logs.policyNumber = t.policyNumber
                                      ';
            if ($transStatus) {
                $query = $query . ' Where ' . $transStatus;
                $and = true;
            }
            if ($productStatus) {
                if ($and == true) {
                    $subText = 'and';
                } else {
                    $subText = 'Where';
                    $and = true;
                }
                $query = $query . ' ' . $subText . ' ' . $productStatus;
            }
            if ($policyStatus) {
                if ($and == true) {
                    $subText = 'and';
                } else {
                    $subText = 'Where';
                    $and = true;
                }
                $query = $query . ' ' . $subText . ' ' . $policyStatus;
            }

            if ($filterByPolicySMS) {
                if ($and == true) {
                    $subText = 'and';
                } else {
                    $subText = 'Where';
                    $and = true;
                }
                $query = $query . ' ' . $subText . ' ' . $filterByPolicySMS;
            }

            if ($filterByCustomerKyc) {
                if ($and == true) {
                    $subText = 'and';
                } else {
                    $subText = 'Where';
                    $and = true;
                }
                $query = $query . ' ' . $subText . ' ' . $filterByCustomerKyc;
            }

            $query = $query . ' group by policyNumber';
            $results = DB::select(
                $query
            );
            //sms sent
            $policy = $results;
            return DataTables::of($policy)
                ->addColumn('sendId', function ($policy) {
                    return '<input type="checkbox" class="kt-checkbox kt-checkable" data-policyid="' . $policy->id . '" name="policy_id" value="' . $policy->id . '"></br>';
                })
                ->addColumn('name', function ($policy) {
                    if ($policy->firstName != null || $policy->lastName) {
                        return $policy->firstName . ' ' . $policy->lastName;
                    }
                })
                ->addColumn('cellphone', function ($policy) {
                    if ($policy->cellphone != null) {
                        return $policy->cellphone;
                    } else {
                        return null;
                    }
                })
                ->addColumn('KycStatus', function ($policy) {
                    return $policy->compliance;
                })
                ->addColumn('premium', function ($policy) {
                    if ($policy->premium != null) {
                        return $policy->premium;
                    } else {
                        return '0.00';
                    }
                })
                ->addColumn('created_at', function ($policy) {
                    if ($policy->policyCreatedDate != null) {
                        return $policy->policyCreatedDate;
                    }
                })
                ->addColumn('product_name', function ($policy) {
                    if ($policy->prdName != null) {
                        return $policy->prdName;
                    }
                })
                ->addColumn('plan_name', function ($policy) {
                    if ($policy->planName != null) {
                        return $policy->planName;
                    }
                })
                ->addColumn('sms_count', function ($policy) {
                    if ($policy->total != null) {
                        return $policy->total;
                    }
                })
                ->addColumn('sms_date_entry', function ($policy) {
                    if ($policy->sms_created_at != null) {
                        return $policy->sms_created_at;
                    }
                })
                ->rawColumns(['status', 'product_name', 'plan_name', 'sendId', 'premium', 'created_at', 'cellphone', 'sms_count', 'sms_date_entry'])
                ->make(true);
        } catch (Exception $ex) {
            return response()->json(['error' => $ex->getMessage(), $ex->getLine()]);
        }
    }


    public function updatePoliciesPremium()
    {
        $vat = VATChanges::where('status', 'Success')
            ->where('payment_method', 'RealPay')
            ->groupBy('policyNumber')
            ->orderBy('message', 'DESC')
            ->get(array(
                'old_value',
                'new_value',
                'policyNumber'
            ));

        foreach ($vat as $key => $v) {
            $policy = Policy::where('policyNumber', $v->policyNumber)->first(array('id', 'premium', 'storId', 'plan_id', 'product_id'));
            $policy->premium = $v->new_value;
            $saved = $policy->save();
        }
    }

    /**`
     * opens a form for creating new policy
     *
     * @return View policy create form
     */
    public function create(Request $request)
    {
        if (auth::user()->hasPermissionTo('policy-edit')) {
            $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
            $vehicleMakes = DB::table('tb_prmotormakemodels')
                ->selectRaw('DISTINCT s_Make')
                ->get(array('s_Make'));
            $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
            $banks = Banks::all();
            $customers = Customer::get(array('id', 'firstName', 'lastName'));
            if ($request->get('customer') != null) {
                $selectedCustomer = Customer::with(['profile', 'banking'])->where('id', $request->get('customer'))->first();
            } else {
                $selectedCustomer = null;
            }

            $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));
            return view('admin.policy.create', compact('products', 'customers', 'motor_items', 'selectedCustomer', 'vehicle_purpose', 'vehicleMakes', 'banks'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**`
     * policy activation
     *
     * @return View activation page
     */
    public function createTemp(Request $request)
    {
        $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
        $vehicleMakes = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get(array('s_Make'));
        $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
        $banks = Banks::all();
        $customers = Customer::get(array('id', 'firstName', 'lastName'));
        if ($request->get('customer') != null) {
            $selectedCustomer = Customer::with(['profile', 'banking'])->where('id', $request->get('customer'))->first();
        } else {
            $selectedCustomer = null;
        }

        $status = null;
        $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));
        return view('ActivationPage', compact('status', 'products', 'customers', 'motor_items', 'selectedCustomer', 'vehicle_purpose', 'vehicleMakes', 'banks'));
    }

    /**`
     * Show a policy edit page for updating values.
     *param: policy id
     * @return View policy edit page
     */
    public function edit(Request $request, Policy $policy)
    {
        $policyactivatecancelleddates = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first('cancelled_date');
        $fileNames = Lookup::where('key', 'file_type')->get(array('key', 'value'));
        $cancelNote = PolicyCoverCancelNote::where('policy_id', $policy->id)
            ->where('doc_type', 'Cancel')
            ->orderBy('id', 'DESC')
            ->first(array('path'));

        $policy_term = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','desc')->get();

        $feedback = CustomerFeedback::where('policy_id',$policy->id)->orderBy('id','desc')->first();

        $coverNote = PolicyCoverCancelNote::where('policy_id', $policy->id)
            ->where('doc_type', 'Cover')
            ->orderBy('id', 'DESC')
            ->first(array('path'));

        $years = range(1990,Carbon::now()->year);
        $banks['names'] = Banks::all();
        $claims = Claim::where('policy_id', $policy->id)->get();
        $productPlan = Productplan::where('id', $policy->plan_id)->first(array('name', 'sum_assured'));
        $user = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
        // mati
        $customerVerification = '';
        $customerMati         = '';
        $matiDetails          = array();
        $matiVerifData        = '';

        if ($user->mati_identity != null) {
            $customerMati = CustomerMati::where('identity_id', $user->mati_identity)->orderBy('id', 'DESC')->first();
            if ($customerMati && $customerMati != null) {
                $matiVerifData = json_decode($customerMati->response);
                $customerVerification = CustomerMati::where('verification_id',$customerMati->verification_id)->get();
            }
        }

        if(isset($user->profile->dob)){
            $user->profile->dob = Carbon::parse(str_replace("/", "-", $user->profile->dob))->format('d-m-Y');
        }

        if(isset($user->profile->sourceOfIncome) && $this->isJSON($user->profile->sourceOfIncome)){
            $user->profile->sourceOfIncome = json_decode($user->profile->sourceOfIncome, true);
        }
        // dd($user->profile->sourceOfIncome);

        $pos = strpos($policy->billingStartDate, '/');
        if ($pos !== false) {
            $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
        } else {
            $policy->billingStartDate = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
        }
        // $user->profile->license_valid_till = Carbon::parse($user->profile->license_valid_till)->format('d-m-Y');
        $user->profile->state_name = State::where('id', $user->profile->state)->first('name');
        // $user->profile->city_name  = City::where('id', $user->profile->city)->first('name');
        $kyc = KYC::where('customer_id', $policy->customer_id)->first();
        if($kyc && $kyc->passportIssuingCountry)
            $passpostIssueCountry = Country::where('id', $kyc->passportIssuingCountry)->first(array('name'));
        else
            $passpostIssueCountry = null;

        $countries = Country::get(array('id', 'name'));
        $states    = State::where('country_id', 28)->get(['id', 'name']);
        $cities    = City::where('state_id', $user->profile->state)->get(['id', 'name']);

        $product = Product::where('id', $policy->product_id)->first(array('id', 'name', 'product_type_id', 'is_motor_items', 'premium_type_id', 'region_id', 'has_vehicle'));
        $productType = ProductType::where('id', $product->product_type_id)->first('name');
        $policyFactors = PolicyFactor::where('policy_id', $policy->id)->get();
        $emailDocs = Documents::where('product_id', $policy->product_id)
            ->orWhere('product_id', -1)
            ->where('status', 1)
            ->get(array('name', 'link','status','product_id'));

        $emailDocs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));

        $EmailDocsPolicyBundled = PolicyBundled::where('policy_id',$policy->id)->get(array('policyDocument'));

        if ($productType->name == 'Instant') {
            $isProductTypeInstant = 'yes';
        } else {
            $isProductTypeInstant = 'no';
        }
        $productFactors = FactorMain::with('value')->where('product_id', $policy->product_id)->get(array('id', 'name', 'type'));
        foreach ($productFactors as $productFactor) {
            $policyFactors = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $productFactor->id)->get(array('factor_value_id', 'value_name', 'name'));
            if ($productFactor->type == 'Input Field') {
                $productFactor->policyFactors = $policyFactors->pluck('value_name')->toArray();
            } else {
                $productFactor->policyFactors = $policyFactors->pluck('factor_value_id')->toArray();
            }
            $productFactor->policyFactorsValueName = $policyFactors->pluck('value_name')->toArray();
        }
        $members       = PolicyMember::where('policy_id', $policy->id)->get();
        $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();

        $policyCover   = PolicyCoverage::where('policy_id', $policy->id)->get(array('id', 'main', 'coverage_value', 'discount', 'type', 'value'));

        $banking       = CustomerBanking::where('policy_id', $policy->id)->first();

        if($banking != null)
        {
            if (isset($banking->billing) && $banking->billing == 'RealPay')
            {
                $banks['branches'] = BankBranches::where('bank_id', $banking->bankName)->orderBy('name','ASC')->get();
            }
        }

        $policy_cellphone = PolicyCellPhone::where('policy_id', $policy->id)->get();
        if($policy_cellphone != null)
        {
            foreach ($policy_cellphone as $device) {
                $device->brands = DeviceMakeModel::where('device_type', $device->device_type)->where('make_id', NULL)->get(array('id', 'name'))->toArray();
                $make           = DeviceMakeModel::where('device_type', $device->device_type)->where('name', $device->cell_phone_make)->first(array('id'));
                $device->models = DeviceMakeModel::where('make_id', $make->id)->get(array('id', 'name'))->toArray();
            }
        }
        $premiumCalcDetails = null;
        $reratedPremiumQuotes = null;
        $dataModel = null;
        $dataMake = null;
        $vehicleMakes = '';
        $vehicleModels = '';
        $agent_name = '';
        $vehicle = '';
        $vehicle_purpose = '';
        $PolicyCoverages = '';

        if($product->id == 6){
            $vehiclePolicyTyreRim = PolicyTyreRim::where('policy_id', $policy->id)->get();
            $vehicleDetailsTyreRim = Vehicle::where('policy_id', $policy->id)->get();
            $vehiclePurposeTyreRim = Lookup::where('key', 'vehicle_purpose')->whereIn('id', [29,139,140])->get(array('id', 'value'));
           // dd($vehicleDetailsTyreRim);
        }else{
            $vehiclePolicyTyreRim = NULL;
            $vehicleDetailsTyreRim = NULL;
            $vehiclePurposeTyreRim = NULL;
        }

        $PolicyBundled = PolicyBundled::where('policy_id',$policy->id)->get();
        if($policy->is_bundled == 1)
         $p_idddd = 0;
        foreach($PolicyBundled as $PolicyBundleds){
            if($PolicyBundleds->product_id == 3)
            $p_idddd = 3;
        }

        if($product->id != 6){
            if($policy->has_vehicle != 0 || ($policy->is_bundled == 1 && $p_idddd == 3) ){
                $vehicle = Vehicle::where('policy_id', $policy->id)->get();
                foreach($vehicle as $vehicles)
                if ($vehicle) {
                    $Terms_id = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','desc')->first('id');
                    if($Terms_id != NULL)
                    $PolicyCoverages = PolicyCoverages::where('vehicle_id', $vehicles->id)->where('policy_id', $policy->id)->where('term_id', $Terms_id->id)->orderBy('id','desc')->get();
                    else
                    $PolicyCoverages = PolicyCoverages::where('vehicle_id', $vehicles->id)->where('policy_id', $policy->id)->orderBy('id','desc')->get();

                    $vehicleMakes = DB::table('tb_prmotormakemodels')
                        ->selectRaw('DISTINCT s_Make')
                        ->get(array('s_Make'));
                    if ($vehicles && $vehicles->make != null) {
                        $vehicleModels = DB::table('tb_prmotormakemodels')->where('s_Make', $vehicles->make)
                            ->selectRaw('DISTINCT s_Variant')
                            ->get(array('s_Variant'));
                    } else {
                        $vehicleModels = DB::table('tb_prmotormakemodels')
                            ->selectRaw('DISTINCT s_Variant')
                            ->get(array('s_Variant'));
                    }
                    $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
                }
            }

        }

        $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));
        //$agents = User::role('Agent')->where('active', 1)->get(array('id', 'firstName', 'lastName'));

        $agents_stores = Stores::orderBy('name')->get(array('id', 'name'));

        if ($policy->agent_id != null) {
            $agent_name = User::leftJoin('agencies', 'agencies.id', 'users.agency_id')
                ->where('users.id', $policy->agent_id)
                ->first(array('users.firstName', 'users.lastName', 'agencies.id as agency_id', 'agencies.status as agency_status', 'agencies.name as agency_name', 'bypass_500k'));
        }

        $is_renewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first('is_renewed');
        $scheduleTransactionCount = ScheduleTransaction::where('policy_number',$policy->policyNumber)->count();
        $terms = PolicyTerm::where('policy_id',$policy->id)->where('trans_type','RENEW')->where('status','Active')->orderBy('id','desc')->first();
        if (isset($is_renewal) && isset($terms) && $is_renewal->is_renewed == 1) {
            // $now = Carbon::now();
            // $term_date =  date('Y-m-d', strtotime($terms->term_end_date));

            $term_date=strtotime($terms->term_end_date);
            $term_date=\Carbon\Carbon::parse($term_date);

            $now=\Carbon\Carbon::now();
            $months = $term_date->diffInMonths($now);
            if($months == 3){
                $is_renewal->is_renewed = 0;
            }
        }

        // if($policy->product_id == 3){
        //     if($policy->policyActivatedDate != null){
        //         $days = Carbon::parse($policy->policyActivatedDate)->diffInDays(Carbon::now());
        //         if($days >= 274){
        //             $is_renewal = 1;
        //         }else{
        //             $is_renewal = 0;
        //         }
        //     }else{
        //         $payment = PaymentTransaction::where('policyNumber',$policy->policyNumber)->first(array('paymentDate'));
        //         if($payment){
        //             $days = Carbon::parse($payment->paymentDate)->diffInDays(Carbon::now());
        //             if($days >= 274){
        //                 $is_renewal = 1;
        //             }else{
        //                 $is_renewal = 0;
        //             }
        //         }else{
        //             $is_renewal = 0;
        //         }
        //     }
        // }else{
        //     $is_renewal = 0;
        // }

        $policyMotorItems = PolicyMotorItems::where('policy_id', $policy->id)->get(array('id', 'item_name', 'item_value', 'policy_id'));
        $count = count($policyMotorItems);
        $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));
        $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status', 'referenceNumber'));

        if ($transaction == null)
            $transaction = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status', 'referenceNumber'));

        $purpose = '';
        $quote_id = '';
        if ($policy->quoteNumber != null){
            $purpose = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('purpose'));
            $quote_id = Quote::where('quoteCode', $policy->quoteNumber)->first(array('id','quoteCode'));
        }else{
            $purpose = null;
            $quote_id = null;
        }

        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        $premium = $policy->premium;

        if ($policy->storeID != null) {
            $store = Stores::where('id', $policy->storeID)->first();
            if ($store && $store->name) {
                $storeName = $store->name;
            } else {
                $storeName = null;
            }
        } else {
            $storeName = null;
        }
        //        if ($product->premium_type_id == 11 && $product->id != 3) {
        //            $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));
        //            $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
        //        } else {
        //            $premium = ($request->premium * ($regionVat / 100)) + $request->premium;
        //            $policy->premium = $premium;
        //            $policy->vat = $request->get('premium') * ($regionVat / 100);
        //            $policy->sum_assured = $request->sum_assured;
        //        }

        if ($product->id == 3 || $product->id == 2) {
            if ($policy->quoteNumber != null) {

                $premiumCalcDetails = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();
                $reratedPremiumQuotes = ReratedPremiumQuote::where('rate_id', $premiumCalcDetails->ratings_id)->orderBy('id', 'DESC')->first();

                 /*$reratingLog = PolicyPremiumReratingLog::where('policy_number',$policy->policyNumber)
                    ->where('status',1)
                    ->where('payment_status',1)
                    ->orderBy('id','DESC')
                    ->first();

                $countLog = PolicyPremiumReratingLog::where('policy_number',$policy->policyNumber)->count();

               $premiumCalcDetails->quoteNumber  = null;
                $premiumCalcDetails->ratings_id = ($reratingLog && $reratingLog->ratings_id) ? $reratingLog->ratings_id : null;
                $premiumCalcDetails->premiumMonthly = ($reratingLog && $reratingLog->month_ins) ? $reratingLog->month_ins : $policy->premium;
                $premiumCalcDetails->premium3Inst = ($reratingLog && $reratingLog->three_ins) ? $reratingLog->three_ins : $policy->premium;
                $premiumCalcDetails->premiumAnnually = ($reratingLog && $reratingLog->annual_ins) ? $reratingLog->annual_ins :$policy->premium;
                $premiumCalcDetails->discount_surcharge = ($reratingLog) ? 'Discount'.'-'.$reratingLog->discount.'/'.'Surcharge'.'-'.$reratingLog->surcharge : null;
                $premiumCalcDetails->premium_rate = ($reratingLog && $reratingLog->annual_ins && $reratingLog->sum_assured) ? number_format(($reratingLog->annual_ins/$reratingLog->sum_assured)*100,2,'.','') : null;
                $premiumCalcDetails->ratio = /*($reratingLog && $reratingLog->) ? $reratingLog-> : null */;


                $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($premiumCalcDetails->is_imported);
                $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($premiumCalcDetails->make, $premiumCalcDetails->is_imported, $premiumCalcDetails->year);
            } else {
                $premiumCalcDetails = null;
                $reratedPremiumQuotes = null;
                $premiumCalcDetails = new \stdClass();

                if (isset($vehicle->first()->is_imported)) {
                    $premiumCalcDetails->is_imported  = $vehicle->first()->is_imported == 0 ? "No" : "Yes";
                } else {
                    $premiumCalcDetails->is_imported  = null;
                }

                if (isset($vehicle->first()->make)) {
                    $premiumCalcDetails->make = $vehicle->first()->make;
                } else {
                    $premiumCalcDetails->make  = null;
                }

                if (isset($vehicle->first()->model)) {
                    $premiumCalcDetails->model = $vehicle->first()->model;
                } else {
                    $premiumCalcDetails->model  = null;
                }

                if (isset($vehicle->first()->year)) {
                    $premiumCalcDetails->year = $vehicle->first()->year;
                } else {
                    $premiumCalcDetails->year  = null;
                }

                $premiumCalcDetails->estimatedValue = $policy->sum_assured;

                if (isset($vehicle->first()->claim_count)) {
                    $premiumCalcDetails->priorAccidents = $vehicle->first()->claim_count;
                } else {
                    $premiumCalcDetails->priorAccidents  = null;
                }

                $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($premiumCalcDetails->is_imported);
                $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($premiumCalcDetails->make, $premiumCalcDetails->is_imported, $premiumCalcDetails->year);
                $premiumCalcDetails->quoteNumber  = null;
                $premiumCalcDetails->ratings_id = null;
                $premiumCalcDetails->premiumMonthly = $policy->premium;
                $premiumCalcDetails->premium3Inst = $policy->premium;
                $premiumCalcDetails->premiumAnnually = $policy->premium;
                $premiumCalcDetails->discount_surcharge = null;
                $premiumCalcDetails->premium_rate = null;
                $premiumCalcDetails->ratio = null;
            }

            $premiumCalcDetails->priorAccidents = Claim::where('policy_id',$policy->id)->count();
            $countLog = PolicyPremiumReratingLog::where('policy_number',$policy->policyNumber)->count();
            $setting = QuoteSettings::orderBy('id','DESC')->first(array('policy_premium_edit_limit'));
            $premiumCalcDetails->can_edit = ($countLog < $setting->policy_premium_edit_limit) ? 1 : 0;
        }

        $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
        if ($balance != null) {
            $balance = $balance->balance;
        } else {
            $balance = 0;
        }

        // For reinstate policy

        $date=\AlphaDirect\PolicyActivateCancelledDate::where('policyNumber',$policy->policyNumber)->first('cancelled_date');
        if($date != null)
        {

            $cancel_date=strtotime($date['cancelled_date']);
            //$cancel_date=Carbon::createFromFormat('Y-m-d', $cancel_date)->format('Y-m-d H:i:s');
           $cancel_date=\Carbon\Carbon::parse($cancel_date);

            $check=\Carbon\Carbon::now();
             $reinstate_days=$cancel_date->diffInDays($check);
            //$days=0;

        }else{
            $cancel_date=$policy->updated_at;
            if($cancel_date == NULL)
            {
                $reinstate_days=0;
            }else{
               // $cancel_date=\Carbon\Carbon::now();
                $check=\Carbon\Carbon::now();
                $reinstate_days=$cancel_date->diffInDays($check);
            }
        }

        $days_to_reinstate=\AlphaDirect\Lookup::where('key','days_to_resinstate')->first('value');
        $functionality='Reinstate';

        /** End for reinstate */

        $policies_reinstate = PolicyReinstate::where('policy_id',$policy->id)->orderBy('id','desc')->first();
        $balance_fresh = null;
        $balance_arrears = null;
        $balanceArrears = null;
        if(isset($policy) && $policy->status == 2){
            $balance_arrears = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(array('balance'));
            if (isset($balance_arrears)) {
                $balanceArrears = number_format(abs($balance_arrears->balance), 2, '.', '');
            } else {
                $balanceArrears = 0;
            }

            // $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
            $balance_fresh = null;
            // if (isset($data)) {
            //     $balance_fresh = $data->new_value;
            // } else {
                $balance_fresh = $policy->premium;
            // }

        }

        $annual_Premium = null;
        $monthly_Premium = null;
        $three_Installment = null;
        $total_premium = null;

        if (isset($policy->premium) && isset($policy->premium_freq)) {
            $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
            $total_premium = round($total_premium['annual'],2);
            // dd($total_premium);

            if ($policy->premium_freq == 1) {
                $monthly_Premium = $policy->premium;
            } else {
                $policyCon = new PolicyController();
                $premium = $policyCon->getMonthlyPrem(3,$total_premium);
                $monthly_Premium = round($premium,2);
            }

            if ($policy->premium_freq == 2) {
                $three_Installment = $policy->premium;
            } else {
                $premium = $total_premium / 3;
                $three_Installment = round($premium,2);
            }

            if ($policy->premium_freq == 3) {
                $annual_Premium = $policy->premium;
            } else {
                $annual_Premium = $total_premium;
            }

        }
        $PolicyBundled = PolicyBundled::where('policy_id',$policy->id)->get();

        $reratelogData = null;
        $motorcompData = null;
        if ($product->id == 3) {
            if ($policy->quoteNumber != null) {
                $motorcompData= MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();
                if (isset($motorcompData)) {
                    $reratelogData = PolicyPremiumReratingLog::where('ratings_id',$motorcompData->ratings_id)->orderBy('id','desc')->first();
                }
            }
        }

        $paymentDetails = PaymentTransaction::where('policyNumber',$policy->policyNumber)->get(array('id','referenceNumber','amount','paymentDate'));
        $policyRenewalButton = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();

        if (($policy->agent_id == auth()->user()->id) || $policy->agent_id == null || auth::user()->hasPermissionTo('policy-Full List') || $policy->agent_id == '0') {
            if (auth::user()->hasPermissionTo('policy-edit')) {
                if ($product->has_vehicle == 1) {
                    return view('admin.policy.edit', compact('paymentDetails','reratelogData','scheduleTransactionCount','balanceArrears','EmailDocsPolicyBundled','vehiclePolicyTyreRim','policyRenewalButton','vehicleDetailsTyreRim','vehiclePurposeTyreRim','balance_arrears','balance_fresh','policies_reinstate','policyactivatecancelleddates','PolicyCoverages','PolicyBundled','annual_Premium','three_Installment','monthly_Premium','policy_term','customerMati','matiDetails','matiVerifData','quote_id','fileNames','balance', 'feedback','years','states', 'cities', 'cancelNote', 'coverNote', 'purpose', 'storeName', 'emailDocs', 'vehicleMakes', 'passpostIssueCountry', 'vehicleModels', 'claims', 'productPlan', 'premium', 'policyFactors', 'count', 'agent_name', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name','agents_stores', 'policyMotorItems', 'motor_items', 'transaction', 'isProductTypeInstant', 'banking', 'banks', 'countries', 'policy_cellphone', 'dataModel', 'dataMake', 'reratedPremiumQuotes', 'premiumCalcDetails','is_renewal','reinstate_days','days_to_reinstate','functionality'));
                } else {
                    return view('admin.policy.edit', compact('paymentDetails','reratelogData','scheduleTransactionCount','balanceArrears','EmailDocsPolicyBundled','vehiclePolicyTyreRim','policyRenewalButton','vehicleDetailsTyreRim','vehiclePurposeTyreRim','balance_arrears','balance_fresh','policies_reinstate','policyactivatecancelleddates','PolicyCoverages','PolicyBundled','annual_Premium','three_Installment','monthly_Premium','policy_term','customerMati','matiDetails','matiVerifData','quote_id','fileNames','balance', 'feedback','years','states', 'cities', 'cancelNote', 'coverNote', 'purpose', 'storeName', 'emailDocs', 'vehicleMakes', 'passpostIssueCountry', 'vehicleModels', 'claims', 'productPlan', 'premium', 'policyFactors', 'count', 'agent_name', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name','agents_stores','policyMotorItems', 'motor_items', 'transaction', 'isProductTypeInstant', 'banking', 'banks', 'countries', 'policy_cellphone','is_renewal','reinstate_days','days_to_reinstate','functionality'));
                }
            } elseif (auth::user()->hasPermissionTo('policy-list')) {
                return view('admin.policy.policyDetails_View', compact('scheduleTransactionCount','balanceArrears','vehiclePolicyTyreRim','EmailDocsPolicyBundled','vehicleDetailsTyreRim','policyRenewalButton','vehiclePurposeTyreRim','balance_arrears','balance_fresh','policies_reinstate','policyactivatecancelleddates','PolicyBundled','policy_term','quote_id','fileNames','balance', 'feedback','years','storeName', 'cancelNote', 'coverNote', 'purpose', 'emailDocs', 'vehicleMakes', 'passpostIssueCountry', 'vehicleModels', 'claims', 'productPlan', 'premium', 'policyFactors', 'count', 'agent_name', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name', 'policyMotorItems', 'motor_items', 'transaction', 'banking', 'banks', 'countries', 'policy_cellphone','is_renewal','reinstate_days','days_to_reinstate','functionality'));
            } else {
                return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
            }
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    // attachment

    public function attachmentUpload(Request $request, $id)
    {
        $data = array();
        $names = $request->name;
        $types = $request->type;
        $files = $request->attachment_file;
        foreach ($types as $key => $type)
        {
            $fileStore = array();
            $attachment = new PolicyAttachments();
            $attachment->name = $names[$key];
            $attachment->policy_id = $id;
            $attachment->type = $type;
            if ($files != NULL)
            {
                $fileStore = array();
                foreach ($files[$key] as $file)
                {
                    $name = preg_replace('/\s+/', '', $file->getClientOriginalName());
                    $filePath = 'MIS/' . 'Policy' . '/' . 'Attachment' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $fileStore[] = $filePath;
                }
            }
            $attachment->attachment = serialize($fileStore);
            $attachment->save();
        }

        activity('Policy Attachment')
            ->performedOn($attachment)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Attachment Stored Successfully');
        return redirect()->back()->with('success', 'Attachment stored Successfully');
    }

    public function attachmentData($id)
    {
        $data = PolicyAttachments::where('policy_id', $id)->get(array('id','policy_id','name','type','attachment'));
        //dd($data);
        return DataTables::of($data)
            ->addColumn('attachment', function ($data)
            {
                if ($data->attachment == NULL)
                {
                    $images = '<img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="200px" height="auto" >';
                } else
                    {
                    $images = '';

                    foreach (unserialize($data->attachment) as $k => $file)
                    {
                        $images .= '
                                        <div class="kt-avatar" style="clear: left; display: inline-block" id="attachment_'. $data['id']. $k.'">
                                            <a href="' . \AlphaDirect\Helper::getCloudFrontURL($file) . '" target="_blank" download=""  >';
                        $images .=  '<img style="width: 100px;height: 100px;" src="';

                        if (pathinfo($file, PATHINFO_EXTENSION) == 'pdf')
                        {
                            $images .=  asset('images/pdf.ico') ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'docx' || pathinfo($file, PATHINFO_EXTENSION) == 'doc' || pathinfo($file, PATHINFO_EXTENSION) == 'docm')
                        {
                            $images .=  asset('images/word.ico') ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'xls' || pathinfo($file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($file, PATHINFO_EXTENSION) == 'csv')
                        {
                            $images .=  asset('images/excel.png') ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($file, PATHINFO_EXTENSION) == 'png')
                        {
                            $images .=  \AlphaDirect\Helper::getCloudFrontURL($file) ;
                        }
                        elseif (pathinfo($file, PATHINFO_EXTENSION) == 'gif')
                        {
                            $images .=  str_replace(env('AWS_URL'), env('AWS_CLOUDFRONT'), Storage::disk('s3')->url($file)) ;
                        }
                        else
                        {
                            $images .=  asset('images/doc.png');
                        }

                    $images .= '">';
                    $images .= '</a>';
                    $images .= '<span class="kt-avatar__cancel removeAttachment" data-toggle="kt-tooltip" title="delete attachment" data-claim-id="'. $data['id'].'"  data-id="'. $k.'" style="display:block; top: -5px; bottom: auto;text-align: center;"> <i class="fa fa-times" style=""></i>
                                </span>
                            </div>
                        ';
                    }
                    $images .= "</a>";
                }
                return $images;
            })
            ->addColumn('actions',function($data) {
                $actions ='';
                if(auth::user()->hasPermissionTo('attachment-delete')) {
                    $actions .= '<a href="" value="'.$data->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                        <i class="la la-trash"></i>
                    </a>';
                }

                return $actions;
            })
            ->rawColumns(['attachment','actions'])
            ->make(true);
    }

    public function discountSurchargePolicyTable($id)
    {
        $data = PolicyDiscountSurcharge::where('policy_id',$id)->get(array('id','policy_id','discount','surcharge','old_value','new_value','total_dis_surc','user_id'));

        return DataTables::of($data)

            ->editColumn('discount', function ($data) {
                if ($data->discount)
                    return $data->discount;
                else
                    return '-';
            })
            ->editColumn('surcharge', function ($data) {
                if ($data->surcharge)
                    return $data->surcharge;
                else
                    return '-';
            })
            ->editColumn('old_value', function ($data) {
                if ($data->old_value)
                    return $data->old_value;
                else
                    return '-';
            })
            ->editColumn('new_value', function ($data) {
                if ($data->new_value)
                    return $data->new_value;
                else
                    return '-';
            })
            ->editColumn('total_dis_surc', function ($data) {
                if ($data->total_dis_surc)
                    return $data->total_dis_surc;
                else
                    return '-';
            })

            ->editColumn('policy_id', function ($data) {
                $policyNumber = Policy::where('id', $data->policy_id)->first();
                if ($policyNumber != null) {
                        $return = '<span class="kt-font-bold kt-font-primary">' . $policyNumber->policyNumber . '</span>';
                        return $return;
                } else {
                    $return = '-';
                    return $return;
                }
            })
            ->editColumn('user_id', function ($data) {
                $user = User::where('id', $data->user_id)->first();
                if ($user != null) {
                        $return = '<span class="kt-font-bold kt-font-primary">' . $user->firstName .' '. $user->lastName . '</span>';
                        return $return;
                } else {
                    $return = '-';
                    return $return;
                }
            })
            ->rawColumns(['actions','user_id','policy_id'])
            ->make(true);
    }

    public function policyEarnedPremium($id)
    {
        $data = Policy::where('id',$id)->first(array('policyNumber'));

        return DataTables::of($data)

            ->make(true);
    }

    public function smsEmailData($id)
    {
        $policy = Policy::where('id',$id)->first(array('policyNumber'));
        $data = SMSEmailLogs::where('policyNumber',$policy['policyNumber'])->get(array('id','policyNumber','type','message_id','message','content','hook','attachments','to_email','to_cellphone','status','created_at'));

        return DataTables::of($data)
            ->editColumn('message_id', function ($data) {
                if ($data->message_id != null) {
                        $return = strip_tags($data->message_id);
                        return $return;
                } else {
                    $return = '-';
                    return $return;
                }
            })
            ->editColumn('message', function ($data) {
                if ($data->message != null) {
                        $return = strip_tags($data->message);
                        return $return;
                } else {
                    $return = '-';
                    return $return;
                }
            })
            // ->editColumn('attachments', function ($data) {
            //     if ($data->attachments != null) {
            //             $return = strip_tags($data->attachments);
            //             return $return;
            //     } else {
            //         $return = '-';

            //         return $return;
            //     }
            // })

            ->addColumn('send_to',function($data) {
                $send_to ='';
                if ($data->type=='Email')
                    return $data->to_email;
                elseif($data->type=='SMS')
                    return $data->to_cellphone;
                else
                    return '-';
            })

            ->editColumn('created_at', function ($data) {
                if ($data->created_at != null) {
                        $created_at = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('Y-m-d h:i A');
                        return $created_at;
                } else {
                    $created_at = '-';
                    return $created_at;
                }
            })

            // ->addColumn('actions',function($data) {
            //     $actions ='';
            //     if(auth::user()->hasPermissionTo('sms-email-log-delete')) {
            //         $actions .= '<a href="" value="'.$data->id.'" class="btn btn-sm btn-clean btn-icon btn-icon-md log-confirm-delete" title="Delete">
            //             <i class="la la-trash"></i>
            //         </a>';
            //     }
            //     return $actions;
            // })
            ->rawColumns(['send_to'])
            ->make(true);
    }

    public function removeAttachment(Request $request)
    {

        $request->validate([
            'claim_id'      => 'required|integer',
            'attachment_id' => 'required|integer'
        ]);

        DB::beginTransaction();
        try{
            if( PolicyAttachments::where('id', $request->claim_id)->exists())
            {
                $data        = PolicyAttachments::where('id', $request->claim_id)->first(['id', 'attachment']);
                $attachments = unserialize($data->attachment);
                if(count($attachments) > 0)
                {
                    //comapare value
                    $removedAttachment = Arr::except($attachments, [$request->attachment_id]);
                    // $removedAttachment = array_values($removedAttachment);  //re-indexing array
                    $data->attachment  = serialize($removedAttachment);
                    $data->save();
                    DB::commit();
                    return response()->json(['status' => true, 'message' => 'Attachment deleted successfully.'], 200);
                }else{
                    return response()->json(['status' => false, 'message' => 'An error has occured.'], 400);
                }
            }else{
                return response()->json('Not found', 500);
            }
         }catch(Exception $ex)
        {
            DB::rollback();
            return response()->json(['status' => false, 'message' => 'An error has occured.'], 400);
        }

    }

    public function getRealPayBranches(Request $request)
    {
        try {
            $branches = BankBranches::where('bank_id', $request->bank_id)->get();
            return response()->json(['code' => 200, 'branches' => $branches], 200);
        } catch (TeacherNotFoundException $e) {

            return response()->json('An error has occured with RealPay get branches', 400);
        }
    }

    public function feedback(Request $request){
        $user                    = User::where('id', auth()->user()->id)->first();
        $feedBack                = new CustomerFeedback();
        $feedBack->customer_id   = $request->customerIdFeedback;
        $feedBack->user_id       = auth()->user()->id;
        $feedBack->cancelled_by  = $user->firstName . ' ' . $user->lastName;
        $feedBack->product_id    = $request->productIdFeedback;
        $feedBack->policy_id     = $request->policyIdFeedback;
        $feedBack->reason        = ($request->reason != null) ? $request->reason : $request->other_reason;
        $feedBack->circumstances = ($request->circum != null) ? $request->circum : $request->other_reason;
        $feedBack->other_company = $request->otherCompany;
        $feedBack->save();

        $check = $this->cancelPolicy($request->policyIdFeedback);
        if ($check == 1)
        {
            $action_user = auth()->user()->id;;
            $action_customer = null;
            // dd($feedBack->policy_id);
            event(new \AlphaDirect\Events\policyLifecycle($feedBack->policy_id,"Cancel",$action_user,$action_customer));
            return Redirect::back()->with('success', 'Feedback submitted successfully');
        }else{
            return Redirect::back()->with('error', 'Feedback failed to submit');
        }
    }

    /**`
     * methods processes claim for valid policy.
     *param: policy id
     * @return View
     */
    public function processClaim($id, $type = null)
    {
        $policy = Policy::where('id', $id)->first(array('has_vehicle', 'has_member', 'id', 'kyc_recipient', 'product_id'));
        $coverages = ProductCoverage::select('id', 'product_id', 'coverage_id', 'name')->where('product_id', $policy->product_id)->get();
        $claims = Claim::where('policy_id', $id)->first();
        $policyCellphone = PolicyCellPhone::where('policy_id', $policy->id)->get(array('cell_phone_make', 'cell_phone_model', 'id'));

        if ($type == 'Glass') {
            $vehicle = Vehicle::where('policy_id', $id)->first(array('front', 'back', 'right', 'left', 'vehicleRegistration'));
            $vehicleMakes = \Illuminate\Support\Facades\DB::table('tb_prmotormakemodels')
                ->selectRaw('DISTINCT s_Make')
                ->pluck('s_Make');
            $supplierTypes = Lookup::where('key', 'supplier_type')->get(array('id', 'value'));
            return view('admin.policy.general', compact('policy', 'vehicleMakes', 'vehicle', 'supplierTypes', 'type', 'claims'));
        } elseif ($type == 'Life') {
            $deathCauses = Lookup::where('key', 'cause_of_death')->get(array('id', 'value'));
            $beneficiaries = PolicyBeneficiary::where('policy_id', $id)->get();
            return view('admin.policy.general', compact('policy', 'type', 'beneficiaries', 'deathCauses', 'claims'));
        } elseif ($type == 'cellphone') {
            $policyCellphone = PolicyCellPhone::where('policy_id', $policy->id)->get(array('cell_phone_make', 'cell_phone_model', 'id'));
            return view('admin.policy.general', compact('policy', 'type', 'policyCellphone', 'claims'));
        } elseif ($type == 'Accident') {
            $vehicle = Vehicle::where('policy_id', $id)->first(array('front', 'back', 'right', 'left', 'vehicleRegistration'));
            $vehicleMakes = \Illuminate\Support\Facades\DB::table('tb_prmotormakemodels')
                ->selectRaw('DISTINCT s_Make')
                ->pluck('s_Make');
            $attorneyRole = User::role('Attorney')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
            $attorney = User::get(array('id', 'firstName', 'lastName'));
            $eventNames = Lookup::where('key', 'motor_claim_event')->get(array('value'));
            $reportedByOpts = Lookup::where('key', 'claim_reported_by')->get(array('value'));
            $lossTypes = Lookup::where('key', 'motor_claim_loss_type')->get(array('value'));
            $claimSubTypes = ClaimSubType::get(array('sub_type'));
            $supplierTypes = Lookup::where('key', 'supplier_type')->get(array('id', 'value'));
            return view('admin.policy.general', compact('coverages', 'claimSubTypes', 'reportedByOpts', 'lossTypes', 'eventNames', 'policy', 'vehicleMakes', 'vehicle', 'attorneyRole', 'attorney', 'supplierTypes', 'type', 'claims'));
        } elseif ($type == "key_loss") {
            $reasons = Lookup::where('key', 'key_loss_claim_reason')->get(array('id', 'value'));
            $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
            return view('admin.policy.general', compact('coverages', 'type', 'reasons', 'policy', 'vehicle_purpose'));
        }elseif ($type == "Legal") {
            return view('admin.policy.general', compact('policy', 'type', 'claims'));
        }
    }

    /**`
     * method stores processed claim
     *param: policy id
     * @return View claim listing page
     */
    public function storeClaim(Request $request)
    {
        $policy = Policy::where('id', $request->get('policy_id'))->first(array('has_vehicle', 'customer_id', 'agent_id', 'kyc_recipient', 'product_id'));
        $customer = Customer::where('id', $policy->customer_id)->first();
        $coverageId = '';
        $coverageName = '';
        if ($request->has('coverage') && isset($request->coverage)) {
            $coverage = explode(':', $request->coverage);
                if (count($coverage) > 0) {
                    if(isset($coverage)) {
                        $coverageId = $coverage[0];
                        $coverageName = $coverage[1];
                    }
                }
            }
        //Latestid for Policy Number
        $latest = Claim::orderBy('id', 'DESC')->first(array('id'));
        if ($latest == null) {
            $latest = collect();
            $latest->id = 0;
        }
        $claim = new Claim();
        $claim->claim_number = 'G' . Carbon::now()->year . str_pad(($latest->id + 1), 6, '0', STR_PAD_LEFT);
        $claim->customer_id = $policy->customer_id;
        $claim->agent_id = $policy->agent_id;
        $claim->created_by = auth()->user()->id;
        $claim->policy_id = $request->get('policy_id');
        $claim->registered_claim = Carbon::parse($request->registered_claim)->format('Y-m-d');
        if ($request->get('type') == 'Glass') {
            $claim->claim_type = 'Glass';
        } elseif ($request->get('type') == "key_loss") {
            $claim->claim_type = 'Key Loss';
        } elseif ($request->get('type') == "cellphone") {
            $claim->claim_type = 'Cellphone';
        }elseif ($request->get('type') == "Legal") {
            $claim->claim_type = 'Legal';
        } elseif ($request->get('type') == 'Life') {
            $claim->claim_type = 'Life';
            if ($request->get('old_beneficiaryId') != null) {
                foreach ($request->get('old_beneficiaryId') as $key => $beneficiaryId) {
                    $b = PolicyBeneficiary::where('id', $beneficiaryId)->first();
                    $b->relation = $request->get('old_beneficiaryRelation')[$key];
                    $b->first_name = $request->get('old_beneficiaryFName')[$key];
                    $b->middle_name = $request->get('old_beneficiaryMName')[$key];
                    $b->last_name = $request->get('old_beneficiaryLName')[$key];
                    $b->dob = $request->get('old_beneficiaryDOB')[$key];
                    $b->gender = $request->get('old_beneficiaryGender')[$key];
                    $b->payment = $request->get('old_beneficiaryPayment')[$key];
                    $b->save();
                }
            }
            if ($request->get('beneficiaries') != null) {
                foreach ($request->get('beneficiaries') as $key => $beneficiaries) {
                    if ($beneficiaries['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->first_name = $beneficiaries['beneficiaryFName'];
                        $b->middle_name = $beneficiaries['beneficiaryMName'];
                        $b->last_name = $beneficiaries['beneficiaryLName'];
                        $b->dob = $beneficiaries['beneficiaryDOB'];
                        $b->gender = $beneficiaries['beneficiaryGender'];
                        $b->payment = $beneficiaries['beneficiaryPayment'];
                        $b->save();
                    }
                }
            }
        } else {
            $claim->claim_type = 'Accident';
        }

        if ($request->get('customer_selected_supplier')) {
            $supplier = new Supplier();
            $supplier->supplierName = $request->get('sname');
            $supplier->supplierType = $request->get('stype');
            $supplier->vat_no = $request->get('vat');
            $supplier->telephone = $request->get('snumber');
            $supplier->email = $request->get('semail');
            $supplier->customer_selected = $request->get('customer_selected_supplier');
            $supplier->supplierLocation = $request->get('slocation');
            $supplier->save();
            $claim->supplier_id = $supplier->id;
            $claim->customer_selected = 1;
        }
        $saved = $claim->save();

        //KYC Update
        if ($policy->kyc_recipient != 0) {
            $recipientKyc = new RecipientKyc();
            $recipientKyc->policy_id = $request->get('policy_id');
            $recipientKyc->claim_id = $claim->id;
            if ($request->hasFile('driving_license')) {
                $file = $request->file('driving_license');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $recipientKyc->driving_license = $filePath;
            }
            if ($request->hasFile('omang')) {
                $file = $request->file('omang');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $recipientKyc->omang = $filePath;
            }
            if ($request->hasFile('proof_residence')) {
                $file = $request->file('proof_residence');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $recipientKyc->proof_residence = $filePath;
            }
            if ($request->hasFile('proof_income')) {
                $file = $request->file('proof_income');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $recipientKyc->proof_income = $filePath;
            }
            if ($request->hasFile('passport')) {
                $file = $request->file('passport');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Recipient' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $recipientKyc->passport = $filePath;
            }
            $recipientKyc->save();
        }
        if ($request->get('type') == 'Glass') {
            $vehicle = Vehicle::where('policy_id', $request->get('policy_id'))->first(array('id'));
            $vehicleClaim = new ClaimVehicle();
            $vehicleClaim->claim_id = $claim->id;
            $vehicleClaim->vehicle_id = $vehicle->id;
            $vehicleClaim->date_of_damage = Carbon::parse($request->incidentDate)->format('Y-m-d');
            $vehicleClaim->damage_extent = $request->extent;
            $vehicleClaim->damage_cause = $request->cause;
            if ($request->hasFile('incidentFront')) {
                $file = $request->file('incidentFront');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->front_image = $filePath;
            }
            $vehicleClaim->front_image_description = $request->front_image_description;
            if ($request->hasFile('incidentBack')) {
                $file = $request->file('incidentBack');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->back_image = $filePath;
            }
            $vehicleClaim->back_image_description = $request->back_image_description;
            if ($request->hasFile('incidentRight')) {
                $file = $request->file('incidentRight');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->right_image = $filePath;
            }
            $vehicleClaim->right_image_description = $request->right_image_description;
            if ($request->hasFile('incidentLeft')) {
                $file = $request->file('incidentLeft');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $vehicleClaim->left_image = $filePath;
            }
            $vehicleClaim->left_image_description = $request->left_image_description;
            $saved = $vehicleClaim->save();
        } elseif ($request->get('type') == 'Life') {
            $life = new ClaimLife();
            $life->claim_id = $claim->id;
            if ($request->hasFile('death_certificate')) {
                $file = $request->file('death_certificate');
                $name = $this->gen_uuid() . $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Claims' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $life->certificate = $filePath;
            }
            $life->date_of_death = Carbon::parse($request->date_of_death)->format('Y-m-d');
            $life->cause_of_death = $request->cause;
            $life->description = $request->description;
            $saved = $life->save();
            if ($saved) {
                return Redirect::route('admin.claims.index')->with('success', 'Life Claim has been processed Successfully');
            }
        }elseif ($request->get('type') == "Legal") {
            $legal = new ClaimLegal();
            $legal->claim_id = $claim->id;
            $legal->legal_firm = htmlspecialchars(strip_tags($request->legal_firm));
            $legal->lawyer_name = htmlspecialchars(strip_tags($request->lawyer_name));
            $legal->legal_tel = htmlspecialchars(strip_tags($request->legal_tel));
            $legal->legal_email = htmlspecialchars(strip_tags($request->legal_email));
            $legal->member_name =  htmlspecialchars(strip_tags($request->member_name));
            // $legal->membership_number = htmlspecialchars(strip_tags($request->membership_number));
            $legal->membership_id = htmlspecialchars(strip_tags($request->membership_id));
            $legal->member_contact = htmlspecialchars(strip_tags($request->member_contact));
            $legal->member_email = htmlspecialchars(strip_tags($request->member_email));
            $legal->lossreported_date = htmlspecialchars(strip_tags($request->lossreported_date));
            $legal->matter_relatesto = htmlspecialchars(strip_tags($request->matter_relatesto));
            $legal->child_financial_dependent = htmlspecialchars(strip_tags($request->child_financial_dependent));
            $legal->idforchild = htmlspecialchars(strip_tags($request->idforchild));
            $legal->child_dob = htmlspecialchars(strip_tags($request->child_dob));
            $legal->realestate_enquiry_from = htmlspecialchars(strip_tags($request->realestate_enquiry_from));
            $legal->matter_quantum = htmlspecialchars(strip_tags($request->matter_quantum));
            $legal->course_of_action = htmlspecialchars(strip_tags($request->course_of_action));
            $legal->criminalmatter_detail = htmlspecialchars(strip_tags($request->criminalmatter_detail));
            $legal->criminalmatter_charge = htmlspecialchars(strip_tags($request->criminalmatter_charge));
            $legal->legaloption = htmlspecialchars(strip_tags($request->legaloption));
            $legal->representing_member = htmlspecialchars(strip_tags($request->representing_member));
            $legal->lawyer_tarrif = htmlspecialchars(strip_tags($request->lawyer_tarrif));
            $legal->arose_date = htmlspecialchars(strip_tags($request->arose_date));
            $legal->jurisdiction = htmlspecialchars(strip_tags($request->jurisdiction));
            $legal->save();
        }
         elseif ($request->get('type') == "key_loss") {
            $keyLoss = new ClaimKeyLoss();
            $keyLoss->claim_id = $claim->id;
            $keyLoss->financial_interest = htmlspecialchars(strip_tags($request->financial_interest));
            $keyLoss->chassis_num = htmlspecialchars(strip_tags($request->chassis_num));
            $keyLoss->purpose = htmlspecialchars(strip_tags($request->purpose));
            $keyLoss->reason = htmlspecialchars(strip_tags($request->reason));
            $keyLoss->replacement_estimate = htmlspecialchars(strip_tags($request->estimate));
            $keyLoss->date_of_loss = Carbon::parse($request->lossDate)->format('Y-m-d');
            $keyLoss->description = htmlspecialchars(strip_tags($request->descriptionofLoss));
            $keyLoss->company_1 = htmlspecialchars(strip_tags($request->company_1));
            $keyLoss->company_2 = htmlspecialchars(strip_tags($request->company_2));
            $keyLoss->amount_quote_1 = htmlspecialchars(strip_tags($request->amount_quote_1));
            $keyLoss->amount_quote_2 = htmlspecialchars(strip_tags($request->amount_quote_2));

            if ($request->hasFile('police_affidavit')) {
                $file = $request->file('police_affidavit');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'police_affidavit' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $keyLoss->police_affidavit = $filePath;
            }
            if ($request->hasFile('quote_1')) {
                $file = $request->file('quote_1');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'quote_1' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $keyLoss->quote_1 = $filePath;
            }
            if ($request->hasFile('quote_2')) {
                $file = $request->file('quote_2');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'quote_2' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $keyLoss->quote_2 = $filePath;
            }
            $keyloss_saved = $keyLoss->save();
            if ($keyloss_saved) {
                return Redirect::route('admin.claims.index')->with('success', 'Key loss Claim has been processed Successfully');
            }
        } elseif ($request->get('type') == 'cellphone') {
            $claimCellphone = new ClaimCellphone();
            $claimCellphone->policy_id = $request->get('policy_id');
            $claimCellphone->claim_id = $claim->id;
            $claimCellphone->damage_extent = htmlspecialchars(strip_tags($request->input('damage_extent', '')));
            $claimCellphone->lossDate = $request->get('lossDate');
            $claimCellphone->mileage = htmlspecialchars(strip_tags($request->input('mileage', '')));
            $claimCellphone->condition = htmlspecialchars(strip_tags($request->input('condition', '')));
            $claimCellphone->imei = htmlspecialchars(strip_tags($request->input('imei', '')));
            $claimCellphone->descriptionofLoss = htmlspecialchars(strip_tags($request->input('descriptionofLoss', '')));
            // $claimCellphone->police_station = htmlspecialchars(strip_tags($request->input('police_station', '')));
            // $claimCellphone->case_number = $request->input('case_number', '');
            $claimCellphone->contact_number = $request->input('contact_number', '');
            $claimCellphone->date_reported = htmlspecialchars(strip_tags($request->input('date_reported', '')));
            // $claimCellphone->ITC_reference_number = htmlspecialchars(strip_tags($request->input('ITC_reference_number', '')));
            $claimCellphone->date_reported_to_alpha = $request->input('date_reported_to_alpha', '');

            if ($request->hasFile('cell_phone_front')) {
                $file = $request->file('cell_phone_front');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->front = $filePath;
            }
            if ($request->hasFile('cell_phone_back')) {
                $file = $request->file('cell_phone_back');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->back = $filePath;
            }
            if ($request->hasFile('cell_phone_left')) {
                $file = $request->file('cell_phone_left');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->left = $filePath;
            }
            if ($request->hasFile('cell_phone_right')) {
                $file = $request->file('cell_phone_right');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->right = $filePath;
            }
            if ($request->hasFile('cell_phone_top')) {
                $file = $request->file('cell_phone_top');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->top = $filePath;
            }
            if ($request->hasFile('cell_phone_bottom')) {
                $file = $request->file('cell_phone_bottom');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Cellphone' . '/' . $claim->id . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $claimCellphone->bottom = $filePath;
            }
            $claimCellphone->save();
        } else {
            $accidentClaims = new ClaimAccident();
            $accidentClaims->claim_id = $claim->id;
            $accidentClaims->claim_type = $request->get('type');
            $accidentClaims->cause = $request->cause;
            $accidentClaims->extent = $request->extent;
            $accidentClaims->recovery_involved = $request->pa_involved;
            $accidentClaims->attorney_involved = $request->attorney_involved;
            $accidentClaims->relation = $request->reported_by;
            $accidentClaims->fault_party = $request->fault_party;
            $accidentClaims->claim_sub_type = $request->claim_sub_type;
            $accidentClaims->loss_type = $request->loss_type;
            $accidentClaims->representative = $request->representative;
            $accidentClaims->catastrophe_loss = $request->catastrophe;
            $accidentClaims->event_name = $request->event_name;
            $accidentClaims->loss_description = $request->loss_description;
            $accidentClaims->primary_attorney_assigned = $request->primary_attorney;
            $accidentClaims->co_attorney_assigned = $request->co_attorney;
            $accidentClaims->claim_number = $request->claim_number;
            $accidentClaims->dfs_complaint = $request->dfs_complaint;
            $accidentClaims->claim_allocated_to = $request->claim_allocated_to;
            $accidentClaims->reserve_amount = $request->reserve_amount;
            $accidentClaims->paid_amount = $request->paid_amount;
            $accidentClaims->gender = $request->gender;
            $accidentClaims->date_of_accident = $request->date_of_accident;
            $accidentClaims->place_of_accident = $request->place_of_accident;
            $accidentClaims->time_of_accident = $request->time_of_accident;
            if ($accidentClaims->attorney_involved == "on") {
                $accidentClaims->attorney_id = $request->attorney;
            }
            if ($request->third_party == null) {
                $accidentClaims->third_party = "0";
            } else {
                $accidentClaims->third_party = "1";

                /* storing third party details */
                if (count($request->get('other_details')) > 0) {
                    foreach ($request->get('other_details') as $key => $details) {
                        // dd($request->all());
                        if (isset($details['third_party_insured'])) {

                            foreach ($details['third_party_insured'] as $key => $value) {
                                $accidentClaims->third_party_insured = "1";
                                if ($value == 1) {
                                    $data = new \stdClass();
                                    $data->customer_id = $claim->customer_id;
                                    // $data->claim_id = $id;
                                    $data->policy_id = $claim->policy_id;

                                    $data->hook = 'claim_third_party_insured';
                                    $data->attachment = NULL;
                                    $email='aprasad@alphadirect.co.bw';
                                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                                    $markdown = new MailTemplate($data);
                                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                                    event(new \AlphaDirect\Events\SendMail($email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));

                                 //   $mailStatus = Mail::to($email)->send(new MailTemplate($data));

                                    // dd($mailStatus);

                                }
                            }

                            if ($details['first_name_insured'] != null) {
                                $OtherPartyInsured = new OtherPartyInsured();
                                if ($claim->id != null) {
                                    $OtherPartyInsured->claim_id = $claim->id;
                                }
                                if (!empty($details['first_name_insured'])) {
                                    $OtherPartyInsured->first_name_insured = $details['first_name_insured'];
                                }else{
                                    $OtherPartyInsured->first_name_insured = null;
                                }
                                if (!empty($details['last_name_insured'])) {
                                    $OtherPartyInsured->last_name_insured = $details['last_name_insured'];
                                }else{
                                    $OtherPartyInsured->last_name_insured = null;
                                }
                                if (!empty($details['cellphone_insured'])) {
                                    $OtherPartyInsured->cellphone_insured = $details['cellphone_insured'];
                                }else{
                                    $OtherPartyInsured->cellphone_insured = null;
                                }
                                if (!empty($details['email_insured'])) {
                                    $OtherPartyInsured->email_insured = $details['email_insured'];
                                }else{
                                    $OtherPartyInsured->email_insured = null;
                                }
                                if (!empty($details['address_insured'])) {
                                    $OtherPartyInsured->address_insured = $details['address_insured'];
                                }else{
                                    $OtherPartyInsured->address_insured = null;
                                }
                                // dd($request->all());
                                $OtherPartyInsured->save();
                            }

                        }


                        if ($details['first_name'] != null) {
                            $thirdparty = new ClaimThirdParty();
                            if ($claim->id != null) {
                                $thirdparty->claim_id = $claim->id;
                            }
                            if (!empty($details['first_name'])) {
                                $thirdparty->first_name = $details['first_name'];
                            }
                            if (!empty($details['last_name'])) {
                                $thirdparty->last_name = $details['last_name'];
                            }
                            if (!empty($details['address'])) {
                                $thirdparty->address = $details['address'];
                            }
                            if (!empty($details['cellphone'])) {
                                $thirdparty->cellphone = $details['cellphone'];
                            }
                            if (!empty($details['plate'])) {
                                $thirdparty->registration_no = $details['plate'];
                            }
                            if (!empty($details['make'])) {
                                $thirdparty->make = $details['make'];
                            }
                            if (!empty($request['model'][$key])) {
                                $thirdparty->model = $request['model'][$key];
                            }
                            if (!empty($details['damage_details'])) {
                                $thirdparty->damage_details = $details['damage_details'];
                            }
                            if (!empty($details['injured_name'])) {
                                $thirdparty->injured_name = $details['injured_name'];
                            }
                            if (!empty($details['relationship'])) {
                                $thirdparty->relationship = $details['relationship'];
                            }
                            if (!empty($details['hospital_name'])) {
                                $thirdparty->hospital_name = $details['hospital_name'];
                            }
                            if (!empty($details['injured_details'])) {
                                $thirdparty->injured_details = $details['injured_details'];
                            }
                            $thirdparty->save();
                        }
                    }
                }
            }
            // $accidentClaims->incident_date = Carbon::parse($request->incidentDate)->format('Y-m-d');
            $accidentClaims->attorney_assigned_date = Carbon::parse($request->attorney_assigned_date)->format('Y-m-d');
            $accidentClaims->co_attorney_assigned_date = Carbon::parse($request->co_attorney_assigned_date)->format('Y-m-d');
            $accidentClaims->claim_allocated_on = Carbon::parse($request->claim_allocated_on)->format('Y-m-d');
            $accidentClaims->first_visit = Carbon::parse($request->first_visit)->format('Y-m-d');
            $saved = $accidentClaims->save();

            /*Entries for Initial Reserve*/
            $claimReserve = new ClaimReserves();
            $claimReserve->claim_id = $claim->id;
            $claimReserve->date = Carbon::now()->format("Y-m-d");
            $claimReserve->transaction_type = 86; /*Id 86 is from look up data,trans_type: initial reserves*/
            $claimReserve->payee = auth()->user()->id;
            $claimReserve->save();

            /*Entries for Initial Reserve*/
            $claimReserveCoverage = new ClaimReservesCoverage();
            $claimReserveCoverage->claim_id = $claim->id;
            $claimReserveCoverage->reserve_id = $claimReserve->id;
            $claimReserveCoverage->reserve_amt = $request->reserve_amount;
            $claimReserveCoverage->balance = $request->reserve_amount;
            $claimReserveCoverage->coverage_id = $coverageId;
            $claimReserveCoverage->coverage_name = $coverageName;
            $claimReserveCoverage->save();

            /* storing driver details*/
            $accidentDriver = new AccidentDriver();
            $accidentDriver->claim_id = $claim->id;
            $accidentDriver->name = $request->driver_name;
            $accidentDriver->address = $request->driver_address;
            $accidentDriver->dob = $request->driver_dob;
            $accidentDriver->cellphone = $request->driver_num;
            $accidentDriver->purpose = $request->driver_purpose;
            $accidentDriver->license = $request->driver_license;
            $accidentDriver->save();


            if ($request->pa_involved  == "on") {
                $recoveryDetails = new ClaimRecoveryInvolved();
                $recoveryDetails->claim_id = $claim->id;
                $recoveryDetails->recovery_name = $request->recovery_name;
                $recoveryDetails->recovery_address = $request->recovery_address;
                $recoveryDetails->recovery_phone = $request->recovery_phone;
                $recoveryDetails->recovery_email = $request->recovery_email;
                $recoveryDetails->recovery_place_employment = $request->recovery_place_employment;
                $recoveryDetails->recovery_work_phone = $request->recovery_work_phone;
                $recoveryDetails->save();
            }

            if($policy->product_id == 3 && $request->get('type') == 'Accident' /*( || $request->get('type') == 'Glass' || $request->get('type') == 'key_loss')*/){
                $claimController = new ClaimsController();
                $category = $claimController->getClaimCategory($claim->id);

                $claim->category = $category;
                $claim->save();
            }

            /* storing passenger injured details*/
            if ($request->get('passenger_injuries') != NULL) {
                foreach ($request->get('passenger_injuries') as $key => $passenger) {
                    $p = new ClaimAccidentPassenger();
                    $p->claim_id = $claim->id;
                    $p->name = $passenger['passenger_name'];
                    $p->address = $passenger['passenger_address'];
                    $p->injury = $passenger['passenger_injury'];
                    $p->save();
                }
            }

            //mail
            if ($customer->email != null) {
                $data = new \stdClass();
                $data->user_id = null;
                $data->customer_id = $claim->customer_id;
                $data->claim_id = $claim->id;
                $data->hook = 'create_claim';
                $data->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['claim_number' =>$claim->claim_number,'hook' => $data->hook]));
            //   Mail::to($user->email)->send(new MailTemplate($data));
            }

        }
        if ($saved) {
            //Helper::ledgerStore($policy->customer_id, 'CLAIM', $claim->id, $policy->product_id, 'CLAIMPAYMENT');
            return Redirect::route('admin.claims.index')->with('success', 'Claim Created Successfully');
        }
    }

    /**`
     * method to store policy data from policy create page
     *
     * @return View policy listing page
     */
    public function store(Request $request)
    {
        if ($request->get('omang') != null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') == null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') != null && $request->get('passport') == null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
        }

        if ($profile == null) {
            $user = new Customer();
            $user->firstName = $request->get('fname');
            $user->lastName = $request->get('lname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('cellphone');
            $user->password = Hash::make('111111');
            $user->save();
            $profile = new CustomerProfile();
            $profile->customer_id = $user->id;
            $profile->gender = $request->get('gender');
            $profile->address = $request->get('address');
            $profile->omang = $request->get('omang');
            $profile->passport = $request->get('passport');
            $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profile->save();
            $kyc = new KYC();
            $kyc->customer_id = $user->id;
            if ($request->hasFile('driving_license')) {
                $file = $request->file('driving_license');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->driving_license = $filePath;
            }
            if ($request->hasFile('omang')) {
                $file = $request->file('omang');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->omang = $filePath;
            }
            if ($request->hasFile('proof_residence')) {
                $file = $request->file('proof_residence');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->proof_residence = $filePath;
            }
            if ($request->hasFile('proof_income')) {
                $file = $request->file('proof_income');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->proof_income = $filePath;
            }
            if ($request->hasFile('passport')) {
                $file = $request->file('passport');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->passport = $filePath;
            }
            $kyc->compliance = 0;
            $kyc->save();
        } else {
            $user = Customer::where('id', $profile->customer_id)->first();
            $user->firstName = $request->get('fname');
            $user->lastName = $request->get('lname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('cellphone');
            $userSaved = $user->save();

            $profile = CustomerProfile::where('customer_id', $profile->customer_id)->first();
            $profile->customer_id = $user->id;
            $profile->gender = $request->get('gender');
            $profile->address = $request->get('address');
            $profile->omang = $request->get('omang');
            $profile->passport = $request->get('passport');
            $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profile->save();
        }
        //Latestid for Policy Number
        $latest = Policy::latest('policyNumber')->orderBy('id', 'DESC')->first(array('policyNumber'));

        if ($latest == null) {
            $latest = collect();
            $latest->id = 0;
        }
        $product = Product::where('id', $request->get('product'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
        $policy = new Policy();
        $policy->customer_id = $user->id;
        $policy->agent_id = Auth::id();
        $policy->note = $request->get('note');
        $policy->leadSource = 'Graphite';
        $policy->billingStartDate = $request->billingStartDate;
        $policy->product_id = $request->get('product');
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;

        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $request->plan)->first(array('sum_assured', 'premium'));
            $latest = Policy::latest('policyNumber')->orderBy('id', 'DESC')->first(array('policyNumber'));
            $premium = ($product_plan->premium * ($regionVat / 100)) + $product_plan->premium;
            $policy->plan_id = $request->get('plan');
            $policy->sum_assured = $product_plan->sum_assured;
            $policy->premium = $product_plan->premium;
            $policy->vat = $product_plan->premium * ($regionVat / 100);
            $policy->vat_percent = $regionVat;
        } else {
            $premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
            $policy->premium = $request->get('premium');
            $policy->vat = $request->get('premium') * ($regionVat / 100);
            $policy->vat_percent = $regionVat;
            $policy->sum_assured = $request->get('sum_assured');
        }

        $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad((substr($latest->policyNumber, -6) + 1), 6, '0', STR_PAD_LEFT);
        $policy->status = 0;
        $policy->has_vehicle = $product->has_vehicle;
        $policy->has_member = $product->has_member;
        $policy->preinspection = $product->preinspection;
        $policy->is_motor_items = $product->is_motor_items;
        $policy->limit = $product->limit;
        $policy->kyc_customer = $product->kyc_customer;
        $policy->kyc_recipient = $product->kyc_recipient;
        $addDays = 0;
        if ($product->has_activation_code) {
            $activation = Activation::where('activation_code', $request->get('activation_code'))->first();
            $activation->product_id = $request->get('product');
            $activation->product_plan_id = $request->get('plan');
            $activation->status = 1;
            $activation->save();
            $policy->activation_code = $request->get('activation_code');
            $policy->serial_code = $activation->serial_code;
            $addDays = $activation->trial_periods;
        }
        $policy->trial_period = Carbon::now()->addDays($addDays)->format('Y-m-d');
        $saved = $policy->save();
        if ($saved == true && $policy->agent_id != null) {
            $data = [
                'id' => $policy->id,
                'agent_id' => $policy->agent_id,
                'status' => $policy->status,
            ];
            event(new CommissionPolicyEvent($data));
        }

        if ($saved == true) {
            $policy = Policy::where('policyNumber', $policy->policyNumber)->first();
            $data = [
                'store_id' => $policy->storeID,
                'product_id' => $policy->product_id,
                'plan_id' => $policy->plan_id,
            ];
            event(new DecrementCounter($data));
        }

        for ($i = 0; $i < count($request->item_name); $i++) {
            if ($request->item_name[$i] != null && $request->item_value[$i] != null) {
                $policyMotorItems = new PolicyMotorItems();
                $policyMotorItems->policy_id = $policy->id;
                $policyMotorItems->item_name = $request->item_name[$i];
                $policyMotorItems->item_value = $request->item_value[$i];
                $policyMotorItems->save();
            }
        }
        $mains = FactorMain::where('product_id', $request->get('product'))->where('status', 1)->get(array('id', 'name', 'type'));
        foreach ($mains as $main) {
            if (is_array($request->get('factor_' . $main->id))) {
                foreach ($request->get('factor_' . $main->id) as $key => $value_id) {
                    $factor = new PolicyFactor();
                    $factor->policy_id = $policy->id;
                    $factor->factor_main_id = $main->id;
                    $factor->name = $main->name;
                    $factor->type = $main->type;
                    $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                    $factor->factor_value_id = $value_id;
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $saved = $factor->save();
                }
            } else {
                $factor = new PolicyFactor();
                $factor->policy_id = $policy->id;
                $factor->factor_main_id = $main->id;
                $factor->name = $main->name;
                $factor->type = $main->type;
                $value = FactorSubType::where('id', $request->get('factor_' . $main->id))->first(array('name', 'factor'));
                if ($main->type == 'Input Field') {
                    $factor->value_name = $request->get('factor_' . $main->id);
                } else {
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $factor->factor_value_id = $request->get('factor_' . $main->id);
                }
                $saved = $factor->save();
            }
        }
        foreach ($request->get('members') as $key => $member) {
            if ($member['relation'] != null) {
                $m = new PolicyMember();
                $m->policy_id = $policy->id;
                $m->relation = $member['relation'];
                $m->first_name = $member['memberFName'];
                $m->last_name = $member['memberLName'];
                $m->dob = Carbon::parse($member['memberDOB'])->format('Y-m-d');
                $m->gender = $member['memberGender'];
                $m->save();
            }
        }
        foreach ($request->get('beneficiaries') as $key => $beneficiary) {
            if ($beneficiary['beneficiaryRelation'] != null) {
                $b = new PolicyBeneficiary();
                $b->policy_id = $policy->id;
                $b->relation = htmlspecialchars(strip_tags($beneficiary['beneficiaryRelation']));
                $b->omang = htmlspecialchars(strip_tags($beneficiary['beneficiaryOmangName']));
                $b->passport = htmlspecialchars(strip_tags($beneficiary['beneficiaryPassName']));
                $b->first_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryFName']));
                $b->last_name = htmlspecialchars(strip_tags($beneficiary['beneficiaryLName']));
                $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                $b->gender = htmlspecialchars(strip_tags($beneficiary['beneficiaryGender']));
                $b->payment = htmlspecialchars(strip_tags($beneficiary['beneficiaryPayment']));
                $b->save();
            }
        }
        $banking = new CustomerBanking();
        $banking->customer_id = $user->id;
        $banking->policy_id = $policy->id;
        $banking->accountNumber = $request->get('accountNumber');
        $banking->billing = $request->get('billingMethod');
        $banking->billingCell = $request->get('billingCell');
        $banking->bankName = $request->get('bankName');
        $banking->branchCode = $request->get('branchCode');
        $banking->accountType = $request->get('bankAccountType');
        $saved = $banking->save();

        $vehicle = new Vehicle();
        $vehicle->customer_id = $user->id;
        $vehicle->policy_id = $policy->id;
        $vehicle->vehiclePlate = $request->vehiclePlate;
        $vehicle->chassisNo = $request->chassisNo;
        $vehicle->odometer = $request->odometer;
        $vehicle->condition = $request->condition;
        $vehicle->purpose = $request->purpose;
        $vehicle->make = $request->make;
        $vehicle->model = $request->model;
        $vehicle->year = $request->date;
        $vehicle->condition = $request->condition;
        $vehicle->cylinders = $request->cylinders;
        $vehicle->cubic_capacity = $request->cubic_capacity;
        $vehicle->seats = $request->seats;
        $vehicle->engineNo = $request->engineNo;
        $vehicle->is_private = $request->is_private;
        $vehicle->is_modified = $request->is_modified;
        $vehicle->is_tracking = $request->is_tracking;
        $vehicle->is_imported = $request->is_imported;

        if ($request->hasFile('front')) {
            $file = $request->file('front');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->front = $filePath;
        }
        if ($request->hasFile('back')) {
            $file = $request->file('back');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->back = $filePath;
        }
        if ($request->hasFile('right')) {
            $file = $request->file('right');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->right = $filePath;
        }
        if ($request->hasFile('left')) {
            $file = $request->file('left');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->left = $filePath;
        }
        if ($request->hasFile('vehicle_valuation')) {
            $file = $request->file('vehicle_valuation');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/valuation' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->vehicle_valuation = $filePath;
        }
        if ($request->hasFile('vehicleRegistration')) {
            $file = $request->file('vehicleRegistration');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->vehicleRegistration = $filePath;
        }
        $saved = $vehicle->save();

        if ($product->type == 'Cellphone') {
            foreach ($request->policycellphone as $key => $cell) {
                $policyCellPhone = new PolicyCellPhone();
                $policyCellPhone->policy_id = $policy->id;
                $policyCellPhone->customer_id = $policy->customer_id;
                $policyCellPhone->device_type = $cell['device_type'];
                $policyCellPhone->imei = $cell['imei'];
                $policyCellPhone->phone_value = $cell['phone_value'];
                $policyCellPhone->cell_phone_make = $cell['cell_phone_make'];
                if ($policyCellPhone->cell_phone_make == 'Other') {
                    $policyCellPhone->cell_phone_make = $cell['other_make'];
                } else {
                    $make = DeviceMakeModel::where('id', $policyCellPhone->cell_phone_make)->first(array('name'));
                    $policyCellPhone->cell_phone_make = $make->name;
                }
                $policyCellPhone->cell_phone_model = $cell['cell_phone_model'];
                if ($policyCellPhone->cell_phone_model == 'Other') {
                    $policyCellPhone->cell_phone_model = $cell['other_model'];
                } else {
                    $model = DeviceMakeModel::where('id', $policyCellPhone->cell_phone_model)->first(array('name'));
                    $policyCellPhone->cell_phone_model = $model->name;
                }
                if (isset($cell['cell_phone_front']) && $cell['cell_phone_front'] != NULL) {
                    $file = $cell['cell_phone_front'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_front = $filePath;
                    }
                }
                if (isset($cell['cell_phone_back']) && $cell['cell_phone_back'] != NULL) {
                    $file = $cell['cell_phone_back'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_back = $filePath;
                    }
                }

                if (isset($cell['cell_phone_left']) && $cell['cell_phone_left'] != NULL) {
                    $file = $cell['cell_phone_left'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_left = $filePath;
                    }
                }
                if (isset($cell['cell_phone_right']) && $cell['cell_phone_right'] != NULL) {
                    $file = $cell['cell_phone_right'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_right = $filePath;
                    }
                }
                if (isset($cell['cell_phone_top']) && $cell['cell_phone_top'] != NULL) {
                    $file = $cell['cell_phone_top'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/top' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_top = $filePath;
                    }
                }
                if (isset($cell['cell_phone_bottom']) && $cell['cell_phone_bottom'] != NULL) {
                    $file = $cell['cell_phone_bottom'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/bottom' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_bottom = $filePath;
                    }
                }
                $policyCellPhone->save();
            }
        }

        if ($request->main != null) {
            for ($i = 0; $i < count($request->main); $i++) {
                $policyCover = new PolicyCoverage();
                $policyCover->policy_id = $policy->id;
                $policyCover->main = $request->main[$i];
                $policyCover->coverage_value = $request->cover_value[$i];
                $policyCover->discount = $request->type[$i];
                $policyCover->type = $request->disccount_type[$i];
                $policyCover->value = $request->type_value[$i];
                $saved = $policyCover->save();
            }
        }

        // Action 1 for Policy Processed
        $transaction = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status'));

        switch ($request->billingOption) {
            case 'VCS':
                $vcs = new PaymentController;
                return $vcs->handlePayment($policy->policyNumber, null);
                break;
            case 'Orange':
                $orangeMoney = new OrangeMoneyController();
                return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                break;
            case 'RealPay':
                $realPay = new RealPayController();
                return $realPay->addClientRealPay($policy, $premium);
                break;
            default:
                return Redirect::back()->with('error', 'Please select a payment vendor')
                    ->withInput($request->all());
        }
        return Redirect::route('admin.policy.index')->with('success', 'Policy Created Successfully');
    }

    /**
     * updates a data from policy edit page
     * policy id ($id)
     * @return View policy listing page
     */
    public function agentUpdate(Request $request)
    {
        if (auth::user()->hasPermissionTo('assign-agent-policy-list') || ('assign-agent-policy-edit')) {
            $agent = Policy::where('id', $request->policy_id)->update(['agent_id' => $request->agent_id]);
            $agent_stores = Policy::where('id', $request->policy_id)->update(['storeID' => $request->storeID]);

            if (($agent) || ($agent_stores) == 1) {
                return redirect()->back()->with('success', 'Updated Successfully');
            }
            else {
                return redirect()->back()->with('error', 'Something went wrong');
            }
            activity('Assign Agent')
                ->performedOn($agent)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Agent Updated');

            activity('Assign Store')
            ->performedOn($agent_stores)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Store Updated');

        } else {
            return redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function makeRefund(Request $request)
    {
        try {
            $policy = Policy::where('policyNumber', $request->policy_number)->first();
            if ($policy == null) {
                return  response()->json('error', 'Policy not found');
            }
            if ($policy->status == 0) {
                return  response()->json('error', 'Deactivated policy is not allowed to refund.');
            }

            $paymentRefund                   = new PaymentTransaction();
            $paymentRefund->paymentMethod    = 'Cash';
            $paymentRefund->referenceNumber  = strtoupper($request->reference_number);
            $paymentRefund->refunded_by      = $request->refunded_by;
            $paymentRefund->policyNumber     = $request->policy_number;
            $paymentRefund->status           = 'Success';
            $paymentRefund->paymentFrequency = 1;
            $paymentRefund->amount           = $request->amount;
            $paymentRefund->reason           = $request->reason;
            $paymentRefund->paymentDate      = Carbon::parse($request->date_of_refund)->format('Y-m-d');
            $paymentRefund->is_refund        = 1;
            $paymentRefund->is_ledger        = 1;
            $paymentRefund->save();

            $data = $paymentRefund->getDirty();

            if ($paymentRefund->save()) {
                $ledger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
                $balance = $ledger->balance;
                $record = array();
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['claim_id'] = NULL;
                $record['banking_id'] = NULL;
                $record['account_name'] = NULL;
                $record['accounting_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $record['trans_type'] = 'Refund';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = strtoupper($request->reference_number);
                $record['orig_trans'] = strtoupper($request->reference_number);
                $record['unallocated'] = NULL;
                $record['system_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Paid';

                $amount = str_replace(',', '',$request->amount);
                $record['debit'] = $amount;
                $record['credit'] = NULL;

                if($balance < 0)
                {
                    $record['balance'] = -1 * (str_replace(',', '',number_format(($amount + abs($balance)), 2)));
                    $balance = -1 * (str_replace(',', '',number_format(($amount + abs($balance)), 2)));
                } else {
                    $record['balance'] = str_replace(',', '',number_format(($balance - $amount), 2));
                    $balance = str_replace(',', '',number_format(($balance - $amount), 2));
                }
                $record['balance'] = $balance;


                //----SUB-LEDGER
                $subRecord = array();

                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = NULL;
                $subRecord['account_name'] = NULL;
                $subRecord['accounting_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $subRecord['trans_type'] = 'Cash Refund';
                $subRecord['trans_ref'] = strtoupper($request->reference_number);
                $subRecord['system_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $subRecord['credit'] = NULL;
                $subRecord['debit'] = number_format($request->amount, 2);

                $subData[] = $subRecord;
                //---------------------------------//
                $subRecord = array();

                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = NULL;
                $subRecord['account_name'] = NULL;
                $subRecord['accounting_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $subRecord['trans_type'] = 'Cash Refund';
                $subRecord['trans_ref'] = strtoupper($request->reference_number);
                $subRecord['system_date'] = Carbon::parse($request->date_of_refund)->format('Y-m-d');
                $subRecord['credit'] = number_format($request->amount, 2);
                $subRecord['debit'] = NULL;

                $subData[] = $subRecord;

                Ledger::insert($record);
                SubLedger::insert($subData);

                DB::commit();

                if (Auth::check()) {
                    activity('Made refund')
                        ->performedOn($policy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Refund made of amount K: ' . $paymentRefund->amount);
                }
                return  redirect()->back()->with('success', 'Refund made successfully.');
            } else {
                return  redirect()->back()->with('error', 'Refund not saved. Try again');
            }
        } catch (Exception $e) {
            DB::rollback();
            return  redirect()->back()->with('error', 'Refund not saved. Try again');
        }
    }

    /*generates unique id*/
    public function uuid(){
        $current_timestamp = Carbon::now()->timestamp;
        return $current_timestamp;
    }

    public function update($id, Request $request)
    {

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $policy = Policy::where('id', $id)->first();
            $policy->note = $request->get('note');
            //$policy->BillingStart = $request->get('BillingStart');

            if($request->get('billingStartDateEdit') != null){
                $policy->billingStartDate = Carbon::parse($request->get('billingStartDateEdit'))->format('Y-m-d');
            }
            if($request->get('policyActivatedDate') != null){
                $policy->policyActivatedDate = Carbon::parse($request->get('policyActivatedDate'))->format('Y-m-d');
            }

            // $policy->agent_id = $request->agent_id;
            $policy->policyDocument = null;
            $saved = $policy->save();

            if($policy->policyNumber != null){
                $policyactivatecancelleddates = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first();
                if(isset($policyactivatecancelleddates )){
                    if($request->get('policyActivatedDate') != null){
                        $policyactivatecancelleddates->activated_date = Carbon::parse($request->get('policyActivatedDate'))->format('Y-m-d');
                        $policyactivatecancelleddates->save();
                    }
                }
            }

            $user = Customer::where('id', $policy->customer_id)->first();
            $user->firstName = $request->get('fname');
            $user->lastName = $request->get('lname');
            $user->middleName = $request->get('mname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('cellphone');

            $getData = $user->getDirty();
            if ($getData) {
                foreach ($getData as $key => $d) {
                    activity('Customer')
                        ->performedOn($user)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log($key . ' updated ' . 'from ' . $user->$key . ' to ' . $d);
                }
            }
            $user->save();

            $profile                         = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            $profile->customer_id            = $user->id;
            $profile->gender                 = $request->get('gender');
            $profile->address                = $request->get('address');
            $profile->omang                  = $request->get('omang');
            $profile->passport               = $request->get('passport');
            $profile->maritalstatus          = $request->get('maritalstatus');
            $profile->driving_license_number = $request->get('driving_license_number');
            $profile->license_valid_till     = $request->get('license_valid_till');
            $profile->countryId              = $request->get('passportIssuingCountry');
            $profile->state                  = $request->get('state');
            $profile->city                   = $request->get('city');
            $profile->dob                    = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profileData                     = $profile->getDirty();
            $profile->e_name                 = $request->get('e_name');
            $profile->emp_no                 = $request->get('emp_no');
            $profile->emp_phone              = $request->get('emp_phone');
            $profile->salary_pay_date        = $request->get('salary_pay_date');

            if ($profileData) {
                foreach ($profileData as $key => $d) {
                    activity('CustomerProfile')
                        ->performedOn($user)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log($key . ' updated ' . 'from ' . $profile->$key . ' to ' . $d);
                }
            }
            $profile->save();
            if ($request->item_name_previous != null) {
                for ($i = 0; $i < count($request->item_name_previous); $i++) {
                    if ($request->item_name_previous[$i] != null) {
                        $updatePolicyMotorItem = PolicyMotorItems::where('id', $request->item_previous_id[$i])->first();
                        $updatePolicyMotorItem->item_name = $request->item_name_previous[$i];
                        $updatePolicyMotorItem->item_value = $request->item_value_previous[$i];
                        $updatePolicyMotorItem->save();
                    }
                }
            }
            if ($request->item_name != null) {
                for ($i = 0; $i < count($request->item_name); $i++) {
                    if ($request->item_name[$i] != null && $request->item_value[$i] != null) {
                        $policyMotorItems = new PolicyMotorItems();
                        $policyMotorItems->policy_id = $policy->id;
                        $policyMotorItems->item_name = $request->item_name[$i];
                        $policyMotorItems->item_value = $request->item_value[$i];
                        $policyMotorItems->save();
                    }
                }
            }
            //For Already added members
            if (is_array($request->get('old_member'))) {
                for ($i = 0; $i < count($request->get('old_member')); $i++) {
                    $m = PolicyMember::where('id', $request->get('old_member')[$i])->first();
                    $m->relation = $request->get('old_relation')[$i];
                    $m->first_name = $request->get('old_memberFName')[$i];
                    $m->last_name = $request->get('old_memberLName')[$i];
                    $m->dob = Carbon::parse($request->get('old_memberDOB')[$i])->format('Y-m-d');
                    $m->gender = $request->get('old_memberGender')[$i];
                    $m->save();
                }
            }
            if ($request->get('members') != null) {
                foreach ($request->get('members') as $key => $member) {
                    if ($member['relation'] != null) {
                        $m = new PolicyMember();
                        $m->policy_id = $policy->id;
                        $m->relation = $member['relation'];
                        $m->first_name = $member['memberFName'];
                        $m->last_name = $member['memberLName'];
                        $m->dob = Carbon::parse($member['memberDOB'])->format('Y-m-d');
                        $m->gender = $member['memberGender'];
                        $m->save();
                    }
                }
            }

            if (is_array($request->get('tyre_id')) && $request->get('tyre_id') != null) {
                // dd($request->all());
                for($i=0; $i < count($request->get('tyre_id')); $i++) {
                    $tyreImg = PolicyTyreRim::where('id', $request->get('tyre_id')[$i])->first();
                    $tyreImg->id = $request->get('tyre_id')[$i];
                    $tyreImg->position = $request->get('tyre_position')[$i];
                    $tyreImg->tyre_insure = $request->get('tyre_insure')[$i];
                    if ($request->hasFile('tyre_image')) {
                        // dd($request->tyre_image[$i]);
                        $file = $request->file('tyre_image');
                        if(array_key_exists($i,$file)){
                            $name = $file[$i]->getClientOriginalName();
                            $filePath = 'MIS/'.$policy->customer_id.'/'.'Tyre'.'/tyre_image'.'/'.Carbon::now().rand(1000,10000).$name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file[$i]), 'public');
                            $tyreImg->image = $filePath;
                        }
                    }
                    $tyreImg->size = $request->get('tyre_size')[$i];
                    $tyreImg->value = $request->get('tyre_value')[$i];
                    $tyreImg->dot_number = $request->get('tyre_dot')[$i];
                    $tyreImg->barcode = $request->get('tyre_barcode')[$i];
                    $tyreImg->updated_at = Carbon::now();
                    $tyreImg->save();
                }
            }

            if (is_array($request->get('vehical_tyre_id')) && $request->get('vehical_tyre_id') != null) {
                // dd($request->all());
                for($i=0; $i < count($request->get('vehical_tyre_id')); $i++) {
                    $vehicletyre = Vehicle::where('id', $request->get('vehical_tyre_id')[$i])->first();
                    $vehicletyre->vehiclePlate = $request->get('tyreVehiclePlate')[$i];
                    $vehicletyre->purpose = $request->get('tyrePurpose')[$i];
                    if ($request->hasFile('tyre_invoice')) {
                        $file = $request->file('tyre_invoice');
                        if(array_key_exists($i,$file)){
                            $name = $file[$i]->getClientOriginalName();
                            $filePath = 'MIS/'.$policy->customer_id.'/'.'Tyre'.'/tyre_invoice'.'/'.Carbon::now().rand(1000,10000).$name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file[$i]), 'public');
                            $vehicletyre->tyre_invoice = $filePath;
                        }
                    }
                    if ($request->hasFile('tyre_km_of_car')) {
                        $file = $request->file('tyre_km_of_car');
                        if(array_key_exists($i,$file)){
                            $name = $file[$i]->getClientOriginalName();
                            $filePath = 'MIS/'.$policy->customer_id.'/'.'Tyre'.'/km_of_car'.'/'.Carbon::now().rand(1000,10000).$name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file[$i]), 'public');
                            $vehicletyre->km_of_car = $filePath;
                        }
                    }
                    $vehicletyre->save();
                }
            }

            if (is_array($request->get('old_beneficiary')) && $request->get('old_beneficiary') != null) {
                for ($i = 0; $i < count($request->get('old_beneficiary')); $i++) {
                    $b = PolicyBeneficiary::where('id', $request->get('old_beneficiary')[$i])->first();

                    $b->first_name = $request->get('old_beneficiaryFName')[$i];
                    $b->last_name = $request->get('old_beneficiaryLName')[$i];
                    $b->omang = $request->get('old_beneficiaryOmang')[$i];
                    $b->passport = $request->get('old_beneficiaryPassport')[$i];
                    $b->dob = Carbon::parse($request->get('old_beneficiaryDOB')[$i])->format('Y-m-d');
                    $b->gender = $request->get('old_beneficiaryGender')[$i];
                    if($request->get('old_beneficiaryRelation') != null || ''){
                        $b->relation = $request->get('old_beneficiaryRelation')[$i];
                    }
                    if($request->get('old_beneficiaryPayment')!= null || ''){
                        $b->payment = $request->get('old_beneficiaryPayment')[$i];
                    }
                    if($request->get('legalEmail')!= null || ''){
                        $b->email = $request->get('legalEmail')[$i];
                    }
                    if($request->get('legalCellphone')!= null || ''){
                        $b->cellphone = $request->get('legalCellphone')[$i];
                    }
                    if($request->get('old_beneficiaryOmangExpiry')!= null || ''){
                        $b->legalOmangExpiry = $request->get('old_beneficiaryOmangExpiry')[$i];
                    }
                    if($request->get('old_beneficiaryPassportExpiry')!= null || ''){
                        $b->legalPassportExpiry = $request->get('old_beneficiaryPassportExpiry')[$i];
                    }
                    $b->save();
                }

                if (Auth::check()) {
                    activity('Beneficiary Updated')
                        ->performedOn($policy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Policy beneficiary updated');
                }
            }
            if (is_array($request->get('beneficiaries')) && $request->get('beneficiaries') != null) {
                foreach ($request->get('beneficiaries') as $key => $beneficiary) {
                    if ($beneficiary['beneficiaryRelation'] != null) {
                        $b = new PolicyBeneficiary();
                        $b->policy_id = $policy->id;
                        $b->relation = $beneficiary['beneficiaryRelation'];
                        $b->first_name = $beneficiary['beneficiaryFName'];
                        $b->middle_name = $beneficiary['beneficiaryMName'];
                        $b->last_name = $beneficiary['beneficiaryLName'];
                        $b->omang = $beneficiary['beneficiaryOmang'];
                        $b->passport = $beneficiary['beneficiaryPassport'];
                        $b->dob = Carbon::parse($beneficiary['beneficiaryDOB'])->format('Y-m-d');
                        $b->gender = $beneficiary['beneficiaryGender'];
                        $b->payment = $beneficiary['beneficiaryPayment'];
                        $b->save();
                    }
                }
                if (Auth::check()) {
                    activity('Beneficiary Added')
                        ->performedOn($policy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Beneficiary added');
                }
            }

            //KYC Update
            $omangKYC = KYC::where('customer_id', $policy->customer_id)->first();

            if($omangKYC != null)
            {
                $clone= new Kycclone();

                    $clone->customer_kyc_id= $omangKYC->id != null ? $omangKYC->id : '';
                    $clone->customer_id= $omangKYC->customer_id != null ? $omangKYC->customer_id : '';
                    $clone->omang=$omangKYC->omang != null ?$omangKYC->omang : '';
                    $clone->omangBack=$omangKYC->omangBack != null ? $omangKYC->omangBack :'';
                    $clone->omangNumber=$omangKYC->omangNumber != null ? $omangKYC->omangNumber :'';
                    $clone->passportNumber=$omangKYC->passportNumber!= null ? $omangKYC->passportNumber: '';
                    $clone->driving_license=$omangKYC->driving_license != null ? $omangKYC->driving_license :'';
                    $clone->omang_front=$omangKYC->omang_front != null ? $omangKYC->omang_front:'' ;
                    $clone->omang_back=$omangKYC->omang_back != null ? $omangKYC->omang_back : '';
                    $clone->proof_residence=$omangKYC->proof_residence != null ?$omangKYC->proof_residence :'';
                    $clone->proof_income=$omangKYC->proof_income != null ? $omangKYC->proof_income :'';
                    $clone->passport=$omangKYC->passport != null ? $omangKYC->passport :'';
                    $clone->omangExpiry=$omangKYC->omangExpiry != null ? $omangKYC->omangExpiry:'';
                    $clone->passportExpiry=$omangKYC->passportExpiry !=null ? $omangKYC->passportExpiry :'';
                    $clone->licenseExpiry=$omangKYC->licenseExpiry != null ? $omangKYC->licenseExpiry :'';
                    $clone->residenceExpiry=$omangKYC->residenceExpiry!= null ? $omangKYC->residenceExpiry :'';
                    $clone->incomeExpiry=$omangKYC->incomeExpiry!= null ?$omangKYC->incomeExpiry:'';
                    $clone->passportIssuingCountry=$omangKYC->passportIssuingCountry != null ? $omangKYC->passportIssuingCountry :'' ;
                    $clone->proof_residence_doc_type=$omangKYC->proof_residence_doc_type != null ? $omangKYC->proof_residence_doc_type : '';
                    $clone->proof_income_doc_type=$omangKYC->proof_income_doc_type != null ? $omangKYC->proof_income_doc_type: '';
                    $clone->omangFrontStatus=$omangKYC->omangFrontStatus != null ? $omangKYC->omangFrontStatus : '';
                    $clone->omangFrontRemark=$omangKYC->omangFrontRemark != null ? $omangKYC->omangFrontRemark :'';
                    $clone->omangBackStatus=$omangKYC->omangBackStatus != null ? $omangKYC->omangBackStatus :'';
                    $clone->omangBackRemark=$omangKYC->omangBackRemark != null ?$omangKYC->omangBackRemark :'' ;
                    $clone->passportStatus=$omangKYC->passportStatus != null ? $omangKYC->passportStatus :'' ;
                    $clone->passportRemark=$omangKYC->passportRemark != null ?$omangKYC->passportRemark:'' ;
                    $clone->driving_licenseStatus=$omangKYC->driving_licenseStatus != null ?$omangKYC->driving_licenseStatus:'';
                    $clone->driving_licenseRemark=$omangKYC->driving_licenseRemark != null ?$omangKYC->driving_licenseRemark:'' ;
                    $clone->proof_residenceStatus=$omangKYC->proof_residenceStatus != null ? $omangKYC->proof_residenceStatus :'';
                    $clone->proof_residenceRemark=$omangKYC->proof_residenceRemark != null ? $omangKYC->proof_residenceRemark:'';
                    $clone->proof_incomeStatus=$omangKYC->proof_incomeStatus !=null ? $omangKYC->proof_incomeStatus :'';
                    $clone->proof_incomeRemark=$omangKYC->proof_incomeRemark != null ? $omangKYC->proof_incomeRemark :'';
                    $clone->compliance=$omangKYC->compliance != null ?$omangKYC->compliance :'';
                    $clone->status=$omangKYC->status != null ?$omangKYC->status:'';
                    $clone->remark=$omangKYC->remark != null ? $omangKYC->remark :'';
                    $clone->performed_by=$omangKYC->performed_by != null ? $omangKYC->performed_by:'';
                    $clone->reason=$omangKYC->reason != null ? $omangKYC->reason :'';
                    $clone->approved_date=$omangKYC->approved_date != null ?$omangKYC->approved_date:'';
                    $clone->created_at=$omangKYC->created_at != null ?$omangKYC->created_at :'';
                    $clone->updated_at=$omangKYC->updated_at != null ? $omangKYC->updated_at :'';
                    $clone->omang_approved_date=$omangKYC->omang_approved_date != null ? $omangKYC->omang_approved_date :'';
                    $clone->passport_approved_date=$omangKYC->passport_approved_date != null ? $omangKYC->passport_approved_date :'';
                    $clone->license_approved_date=$omangKYC->license_approved_date ? $omangKYC->license_approved_date :'';
                    $clone->save();

                    if ($request->get('passportIssuingCountry') != null) {
                        $omangKYC->passportIssuingCountry = $request->get('passportIssuingCountry');
                    }

            if ($request->hasFile('driving_license')) {
                $file = $request->file('driving_license');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/driving_license' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->driving_license = $filePath;
            }

            if ($request->hasFile('omang_pic')) {
                $file = $request->file('omang_pic');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/Omang-Front' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->omang = $filePath;
            }

            if ($request->hasFile('omang_pic_back')) {
                $file = $request->file('omang_pic_back');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/Omang-Back' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->omangBack = $filePath;
            }

            if ($request->hasFile('proof_residence')) {
                $file = $request->file('proof_residence');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/proof_residence' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->proof_residence = $filePath;
            }
            if ($request->hasFile('proof_income')) {
                $file = $request->file('proof_income');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/proof_income' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->proof_income = $filePath;
            }
            if ($request->hasFile('passport_pic')) {
                $file = $request->file('passport_pic');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/passport' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->passport = $filePath;
            }

            if (isset($omangKYC->compliance) && $omangKYC->compliance != 1) {
                $omangKYC->compliance = 0;
            }

            if ($omangKYC->driving_license || $omangKYC->proof_residence || $omangKYC->proof_income || (($omangKYC->omang && $omangKYC->omangBack) || $omangKYC->passport)) {
                if (Auth::check()) {
                    activity('KYC Document')
                        ->performedOn($policy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('KYC document uploaded');
                }
            }

            $omangKYC->save();
        }

            $banking = CustomerBanking::where('customer_id', $policy->customer_id)->where('policy_id', $policy->id)->first();
            if($banking != NULL)
            {
                $banking->accountNumber = $request->get('accountNumber');

                if($request->get('billingMethod'))
                    $banking->billing = $request->get('billingMethod');

                $banking->billingCell = $request->get('billingCell');
                $banking->bankName = $request->get('bankName');
                $banking->branchCode = $request->get('branchCode');
                $banking->accountType = $request->get('bankAccountType');
                $saved = $banking->save();
            }

            if($request->get('vehicalTyreRim') !='6'){
                if ($policy->has_vehicle == '1') {
                    $vehicle = Vehicle::where('policy_id', $policy->id)->first();
                    //dd($vehicle,$request->all());
                    if ($vehicle) {
                        $vehicle->customer_id = $user->id;
                        $vehicle->policy_id = $policy->id;
                        $vehicle->vehiclePlate = $request->vehiclePlate;
                        $vehicle->chassisNo = $request->chassisNo;
                        $vehicle->odometer = $request->odometer;
                        $vehicle->condition = $request->condition;
                        $vehicle->purpose = $request->purpose;

                        if(isset($request->make))
                            $vehicle->make = $request->make;

                        if(isset($request->model))
                            $vehicle->model = $request->model;

                        if(isset($request->date))
                            $vehicle->year = $request->date;

                        $vehicle->condition = $request->condition;
                        $vehicle->cylinders = $request->cylinders;
                        $vehicle->cubic_capacity = $request->cubic_capacity;
                        $vehicle->seats = $request->seats;
                        $vehicle->engineNo = $request->engineNo;
                        $vehicle->is_private = $request->is_private;
                        $vehicle->is_modified = $request->is_modified;
                        $vehicle->is_tracking = $request->is_tracking;
                        $vehicle->is_imported = $request->is_imported;

                        if ($request->hasFile('front')) {
                            $file = $request->file('front');
                            $result = $this->isImageValid($file);
                            if ($result == true) {
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $vehicle->front = $filePath;
                            }
                        }
                        if ($request->hasFile('back')) {
                            $file = $request->file('back');
                            $result = $this->isImageValid($file);
                            if ($result == true) {
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $vehicle->back = $filePath;
                            }
                        }

                        if ($request->hasFile('right')) {
                            $file = $request->file('right');
                            $result = $this->isImageValid($file);
                            if ($result == true) {
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $vehicle->right = $filePath;
                            }
                        }
                        if ($request->hasFile('left')) {
                            $file = $request->file('left');
                            $result = $this->isImageValid($file);
                            if ($result == true) {
                                $name = $file->getClientOriginalName();
                                $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $vehicle->left = $filePath;
                            }
                        } //vehicle_valuation
                        if ($request->hasFile('vehicle_valuation')) {
                            $file = $request->file('vehicle_valuation');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicle' . '/valuation' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $vehicle->vehicle_valuation = $filePath;
                        }
                        if ($request->hasFile('vehicleRegistration')) {
                            $file = $request->file('vehicleRegistration');
                            $name = $file->getClientOriginalName();
                            $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicle' . '/registration' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $vehicle->vehicleRegistration = $filePath;
                        }

                        if($vehicle->status == 2)
                            $vehicle->status = 3;

                        $saved = $vehicle->save();
                    }
                    if ($request->cover_id != null) {
                        for ($i = 0; $i < count($request->cover_id); $i++) {
                            $policyCover = PolicyCoverage::where('id', $request->cover_id[$i])->first();
                            $policyCover->coverage_value = $request->cover_value[$i];
                            $policyCover->discount = $request->type[$i];
                            $policyCover->type = $request->discount_type[$i];
                            $policyCover->value = $request->type_value[$i];
                            $saved = $policyCover->save();
                        }
                    }
                }
            }

            $imeimsg = null;
            if ($request->devices != NULL) {
                foreach ($request->devices as $key => $device) {

                    if (htmlspecialchars(strip_tags($device['imei']))) {
                        $count = PolicyCellPhone::where('imei', htmlspecialchars(strip_tags($device['imei'])))->count();
                        if ($count > 0) {
                            $policyCount = 0;
                            $imeiNos    = PolicyCellPhone::where('imei', htmlspecialchars(strip_tags($device['imei'])))->get();
                            foreach ($imeiNos as $imei) {
                                $checkStatus = Policy::where('id', $imei->policy_id)->first()->status;
                                if ($checkStatus != null || $checkStatus != 2) {
                                    $policyCount = $policyCount + 1;
                                }
                            }
                            if ($policyCount > 0) {
                                $imeimsg = 'IMEI number is already registered';
                            }
                            // return redirect()->back()->with('error','IMEI number is already registered');
                        }
                    }

                    $device_id = htmlspecialchars(strip_tags($device['device_id']));
                    if ($device_id != NULL) {
                        $policyCellPhone = PolicyCellPhone::where('id', $device_id)->first();
                    } else {
                        $policyCellPhone = new PolicyCellPhone();
                        $policyCellPhone->policy_id = $policy->id;
                        $policyCellPhone->customer_id = $policy->customer_id;
                    }
                    $policyCellPhone->device_type = htmlspecialchars(strip_tags($device['device_type']));
                    $policyCellPhone->imei = htmlspecialchars(strip_tags($device['imei']));
                    $policyCellPhone->phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                    $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                    $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                    $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                    if ($make != NULL)
                        $policyCellPhone->cell_phone_make = $make->name;

                    $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                    if ($model != NULL)
                        $policyCellPhone->cell_phone_model = $model->name;

                    if (isset($device['cell_phone_front']) && $device['cell_phone_front'] != NULL) {
                        $file = $device['cell_phone_front'];
                        $result = $this->isImageValid($file);
                        if ($result == true) {
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/front' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $policyCellPhone->cell_phone_front = $filePath;
                        }
                    }
                    if (isset($device['cell_phone_back']) && $device['cell_phone_back'] != NULL) {
                        $file = $device['cell_phone_back'];
                        $result = $this->isImageValid($file);
                        if ($result == true) {
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/back' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $policyCellPhone->cell_phone_back = $filePath;
                        }
                    }
                    if (isset($device['cell_phone_left']) && $device['cell_phone_left'] != NULL) {
                        $file = $device['cell_phone_left'];
                        $result = $this->isImageValid($file);
                        if ($result == true) {
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/left' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $policyCellPhone->cell_phone_left = $filePath;
                        }
                    }
                    if (isset($device['cell_phone_right']) && $device['cell_phone_right'] != NULL) {
                        $file = $device['cell_phone_right'];
                        $result = $this->isImageValid($file);
                        if ($result == true) {
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/right' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $policyCellPhone->cell_phone_right = $filePath;
                        }
                    }
                    if (isset($device['cell_phone_top']) && $device['cell_phone_top'] != NULL) {
                        $file = $device['cell_phone_top'];
                        $result = $this->isImageValid($file);
                        if ($result == true) {
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/top' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $policyCellPhone->cell_phone_top = $filePath;
                        }
                    }
                    if (isset($device['cell_phone_bottom']) && $device['cell_phone_bottom'] != NULL) {
                        $file = $device['cell_phone_bottom'];
                        $result = $this->isImageValid($file);
                        if ($result == true) {
                            $name = $file->getClientOriginalName();
                            $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/bottom' . '/' . $name;
                            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $policyCellPhone->cell_phone_bottom = $filePath;
                        }
                    }
                    $policyCellPhone->save();
                }
            }

            \Illuminate\Support\Facades\DB::commit();
            activity('Policy')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Policy Updated');
            //sms
            $sms = new SmsMessaging();
            $sms = $sms->sendUpdatePolicy(30, $user->cellphone, $user->firstName, $policy->policyNumber);
            activity('Policy Updated SMS')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('SMS send');

                // if($policy->product_id == 3){
                // }
                    $d = new DocumentController();
                    $verificationDoc = $d->generateInformationDocument($policy->id);

                    if ($verificationDoc != null) {
                        $policy->verification_doc = $verificationDoc;
                        $saved = $policy->save();
                    }

            //mail
            if ($user->email != null) {
                $data = new \stdClass();
                $data->user_id = null;
                $data->customer_id = $user->id;
                $data->hook = 'update_policy';
                $data->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,null,['hook' => $data->hook]));
             //   Mail::to($user->email)->send(new MailTemplate($data));
            }

            activity('Policy Updated Email')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Email send');
            // Redirect to the home page with success menu
            return Redirect::route('admin.policy.edit', $id)->with('success', 'Policy Updated Successfully'  . $imeimsg);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reratePolicyPremium(Request $request){
        try{
            $policy         = Policy::where('policyNumber', $request->policyNumber)->first(array('id','quoteNumber','customer_id','product_id','premium','premium_freq','first_premium','sum_assured'));
            $controller     = new \AlphaDirect\Http\Controllers\Admin\CustomerController();
            $stats          = $controller->rateLossStats();
            $ClaimPayment   = $controller->getPolicyClaimPayments($policy->id);
            $make           = $request->get('make');
            $year           = $request->get('year');
            $model          = $request->get('model');
            if(!empty($request->get('dob'))){
                $dob         = date("d/m/Y", strtotime($request->get('dob')));
            }else{
                return response()->json(['success' => 'false','data'=>null,'message'=>'Please provide date of birth'],400);
            }
            $sum_insured    = $request->get('estimatedValue');
            $status         = $request->get('is_imported');
            $marital_status = $request->get('marital');
            $claim_count    = $request->get('prior_accidents');
            $gender         = $request->get('gender');
            $policyNumber   = $request->policyNumber;


            if($marital_status == 1) {
                $updated_marital_status = "Never Married";
            }elseif($marital_status == 2){
                $updated_marital_status = "Married Before";
            }elseif($marital_status == 3){
                $updated_marital_status = "Married Before";
            }elseif($marital_status == 4){
                $updated_marital_status = "Married Before";
            }elseif($marital_status == 5){
                $updated_marital_status = "Never Married";
            }elseif($marital_status == 6){
                $updated_marital_status = "Married Before";
            }else{
                return response()->json(['success' => 'false','data'=>null,'message'=>'Please provide marital status'],400);
            }

            if($gender == 1) {
                $updated_gender = "Male";
            }elseif($gender == 0){
                $updated_gender = "Female";
            }else{
                return response()->json(['success' => 'false','data'=>null,'message'=>'Please provide gender'],400);
            }

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL            => env('RATINGS_URL').'calculation',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => '{
                "make":"'.$make.'",
                "model":"'.$model.'",
                "manufacturing_year":"'.$year.'",
                "dob":"'.$dob.'",
                "sum_insured":"'.$sum_insured.'",
                "status":"'.$status.'",
                "policy_number":"'.$policyNumber.'",
                "claimPayment":"'.$ClaimPayment.'",
                "P1":"'.$stats["P1"].'",
                "C1":"'.$stats["C1"].'",
                "marital_status":"'.$updated_marital_status.'",
                "claim_count":"'.$claim_count.'",
                "gender":"'.$updated_gender.'"
                }',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response,true);

            $logData = [
                "ratings_id"=>$data['rate_id'],
                "policy_number"=>$request->policyNumber,
                "month_ins"=>$data['monthly_premium_vat'],
                "three_ins"=>$data['threemonthly_preminum_vat'],
                "annual_ins"=>$data['result'],
                "customer_marital_status"=>$marital_status,
                "customer_dob"=> Carbon::createFromFormat('d/m/Y', $dob)->format('Y-m-d'),
                "customer_gender"=>$gender,
                "japnese_import"=>$status,
                "make"=>$make,
                "model"=>$model,
                "sum_assured"=>$sum_insured,
                "claim_count"=>$claim_count,
                "manufacturing_year"=>$year,
                "rerated_by"=>$request->get('user_id'),
                'status' => 'Pending',
            ];

            $addRateLog = PolicyPremiumReratingLog::addReratingLog($logData);

            if (!isset($policy->quoteNumber)) {
                $request['customer_id'] = $policy->customer_id;
                $quote = new QuoteController();
                $store = $quote->storeMotorComprehensiveQuoteRenew($request->all(),$logData);
                if($store != false){
                    $quote_update = Policy::where('policyNumber', $request->policyNumber)->first();
                    $quote_update->quoteNumber = $store;
                    $quote_update->save();
                } else {
                    return response()->json(['status' => 'Something went wrong'],401);
                }
            }

            $policy_quote = Policy::where('policyNumber', $request->policyNumber)->first();
            $update = MotorComprehensiveQuotes::where('quoteNumber',$policy_quote->quoteNumber)->orderBy('id','desc')->first();
            // dd($update);
                if($update != null){
                    $update->ratings_id = $data['rate_id'];
                    $update->premiumMonthly = $data['monthly_premium_vat'];
                    $update->premium3Inst = $data['threemonthly_preminum_vat'];
                    $update->premiumAnnually = $data['result'];
                    $update->premium_rate = ($data['result']/$sum_insured)*100;
                    $update->make = $make;
                    $update->model = $model;
                    $update->manufacturingYear = $year;
                    $update->estimatedValue = $sum_insured;
                    $update->priorAccidents = $claim_count;
                    $update->is_imported = $status;
                    $update->discount_surcharge = null;
                    $update->percent_discount_surcharge = null;
                    $update->save();

                    $customerProfile = CustomerProfile::where('customer_id',$update->customer_id)->first();
                    $customerProfile->dob = date("Y-m-d", strtotime($request->get('dob')));;
                    $customerProfile->maritalstatus = $marital_status;
                    $customerProfile->gender = $gender;
                    $customerProfile->save();

                    $add = new ReratedPremiumQuote();
                    $add->quote_number = $policy->quoteNumber;
                    $add->rate_id = $data['rate_id'];
                    $add->old_value = $request->annual_premium;
                    $add->new_value = $data['result'];
                    $add->old_premium = $policy->premium;
                    $add->old_frequency = $policy->premium_freq;
                    $add->old_first_premium = $policy->first_premium;
                    $add->old_sum_insured = $policy->sum_assured;
                    $add->reason = 'Premium Rerating';
                    $add->added_by = Auth::id();
                    $add->ip = $request->ip();
                    $add->save();

                    $renewal = PolicyRenewal::where('policyNumber',$request->policyNumber)->orderBy('id', 'desc')->first();

                    if (isset($renewal)) {
                        $renewal->new_premium = $data["result"];
                        //$renewal->new_quote_number = $quoteNumber;
                        $renewal->is_rated = 1;
                        $renewal->sum_assured = $sum_insured;
                        $renewal->request_data = serialize([
                            'Make : '.$make,
                            'Year: '.$year,
                            'DOB :'.$dob,
                            'Gender :'.$updated_gender,
                            'Marital Status :'.$updated_marital_status,
                            'Estimated Value :'.$sum_insured,
                            'Claim Count : '.$claim_count,
                            'Import Status : '.$status,
                            //'Reserve Total : '.$reserveTotal,
                        ]);
                        $renewal->response_data = serialize($logData);
                        $renewal->save();
                    }

                    $delete = PolicyDiscountSurcharge::where('policy_id',$policy->id)->delete();

                    return response()->json(['success' => 'true','data'=>$data, 'rerateStatus' => $logData['status'],'message'=>'Request is successful'],200);
                }else{
                    return response()->json(['success' => 'false','message'=>'Quote not found with quote number: '.$request->quoteCode],400);
                }

        }catch(\Exception $ex){
            return response()->json(['success' => 'false','data'=>null,'message'=>$ex->getMessage().' '.$ex->getCode()],400);
        }
    }

    public function newPolicyPremium(Request $request){
        try{
            $policyNumber = $request->policyNumber;
            if($policyNumber) {
                $premium = PolicyRenewal::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first();
                if($premium && $premium->is_rated){
                    if($premium->new_premium){
                        $data['policyNumber']       = $policyNumber;
                        $data['monthly_instalment'] = round(($premium->new_premium/12)*1.08,2);
                        $data['three_instalment']   = round($premium->new_premium/3,2);
                        $data['annual_instalment']  = round($premium->new_premium,2);

                        return response()->json(['success' => 'true','data'=>$data,'message'=>'Request is successful'],200);
                    }else{
                        return response()->json(['success' => 'false','data'=>null,'message'=>'Rerated premium not found'],401);
                    }
                }else{
                    return response()->json(['success' => 'false','data'=>null,'message'=>'Rerated premium not found or please rerate the policy'],401);
                }

            }else{
                return response()->json(['success' => 'false','data'=>null,'message'=>'policy number is null'],401);
            }
        }catch(\Exception $ex){
            return response()->json(['success' => 'false','data'=>null,'message'=>$ex->getMessage().' '.$ex->getCode()],401);
        }
    }


    public function getSettingsAPI()
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL            => env('RATINGS_URL').'getSettings',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Cookie: __cfduid=d52d1be0186f72c5ab914ad142d22ba941620644343'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response,true);

            return $data;

        } catch (\Exception $ex) {
            return response()->json(['success' => 'false','message'=>$ex->getMessage().' '.$ex->getLine()],401);
        }
    }

    public function policyDiscountSurchargeAPI(Request $request)
    {
        try {
            $policy = Policy::where('id', $request->policyId)->first(array('id','policyNumber','premium_freq','sum_assured'));
            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = PolicyDiscountSurcharge::where('policy_id', $policy->id)
                    ->where('discount', '!=', 'null')
                    ->where('surcharge', '!=', 'null')
                    ->get()
                    ->count();


            if (((int)$setting->edit_limit >= $edited + 1) == true) {
                $requestData = $request->except('generatePaymentUrl');
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {
                        return response()->json(['success' => 0,'message'=>ucfirst($key) . ' is required'],401);
                    }
                }

                /*Start*/
                $dataDisSur     = PolicyDiscountSurcharge::where('policy_id', $policy->id);
                $totalDisc      = abs($dataDisSur->where('discount', '!=', null)->sum('discount'));
                $totalDisc      = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc      = PolicyDiscountSurcharge::where('policy_id', $policy->id)->where('surcharge', '!=', null)->sum('surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/

                $Role = null;
                if (isset($request->user)) {
                    $Role = User::with('roles')->where('id',$request->user)->first();
                } else {
                    $Role     = auth()->user()::with('roles')->first();
                }

                $userRole = $Role->roles[0]->id;
                $data     = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();
                $old      = PolicyDiscountSurcharge::where('policy_id', $policy->id)->orderBy('id','desc')->first(array('new_value'));

                // $renewals = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
                // if (isset($renewals)) {
                //     $annualPremium = $renewals->new_premium;
                // } else {
                //     $annualPremium = $this->getAnnualPremiumAPI($request->policyId);
                //     // $annualPremium = null;
                // }
                // $annualPremium = $this->getRenewalPremium($policy->policyNumber);

                $annualPremium = $request->annual_premium_rerate;

                if($old) {
                    $oldValue      = $old->new_value;
                    $annualPremium = $old->new_value;
                }
                else {
                    $oldValue = $annualPremium;
                }

                if ($data) {
                    $type                  = $request->type;
                    $value_type            = $request->value_type;
                    $value                 = (float)$request->value;
                    $permittedFlatValue    = ($data->$type) / 100 * $annualPremium;
                    $permittedPercentValue = $data->$type;

                    if ($value_type == 1) {
                        $v_perc = ($value /$annualPremium) * 100;
                        $v_flat = $value;
                    } elseif ($value_type == 2) {
                        $v_perc = $value;
                        $v_flat = ($value / 100) *$annualPremium;
                    } else {
                        DB::rollBack();
                        return response()->json(['success' => 0,'message'=>'Value type not found'],401);
                    }

                    if ($type == 'discount') {
                        if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                            return response()->json(['success' => 0,'message'=>'Can not exceed maximum discount value allowed'],401);
                    }

                    if ($type == 'surcharge') {
                        if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                            return response()->json(['success' => 0,'message'=>'Can not exceed maximum surcharge value allowed'],401);
                    }

                    if ($v_perc <= $permittedPercentValue) {
                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                return response()->json(['success' => 0,'message'=>'Can not exceed maximum discount value allowed'],401);

                            $annual = number_format((float)$annualPremium - $v_flat, 2, '.', '');
                        } elseif ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                return response()->json(['success' => 0,'message'=>'Can not exceed maximum surcharge value allowed'],401);

                            $annual = number_format((float)$annualPremium + $v_flat, 2, '.', '');
                        } else {
                            return response()->json(['success' => 0,'message'=>'Type not found'],401);
                        }

                        if ($type == 'discount') {
                            $dis_value = $v_perc;
                            $sur_value = 0;
                            $flatValue = -($v_flat);
                        } elseif ($type == 'surcharge') {
                            $sur_value = $v_perc;
                            $dis_value = 0;
                            $flatValue = $v_flat;
                        } else {
                            return response()->json(['success' => 0,'message'=>'Type not found'],401);
                        }

                        $logData = [
                            'policy_id'      => $policy->id,
                            'discount'       => $dis_value,
                            'surcharge'      => $sur_value,
                            'old_value'      => $oldValue,
                            'new_value'      => $annual,
                            'total_dis_surc' => $flatValue,
                            'ip_address'     => $request->ip(),
                            'user_id'=> isset($request->user) ? $request->user : auth::user()->id,
                        ];

                        $log = PolicyDiscountSurcharge::addLog($logData);
                        // dd($log);

                        // if ( isset($annual) && $annual < 3192 ) {
                        //     return response()->json(['success' => 0,'message'=>'Sorry ' . $type . ' can not be applied . You have already reached to minimum discounted value.'],401);
                        // }

                        $getSettings = $this->getSettingsAPI();
                        if (isset($getSettings)) {
                            foreach ($getSettings['data'] as $key => $item) {
                                if ($item['id'] == 3) {
                                    if (isset($item['value']) && $annual < $item['value']) {
                                        return response()->json(['success' => 0,'message'=>'Sorry ' . $type . ' can not be applied . You have already reached to minimum discounted value.'],401);
                                    }
                                }
                            }
                        }

                        $policyCon = new PolicyController();
                        $monthly_premium = $policyCon->getMonthlyPrem(3,$annual);
                        $monthly_premium = number_format((float)$monthly_premium, 2, '.', '');

                        $threeintsll_premium = $annual / 3;
                        $threeintsll_premium = number_format((float)$threeintsll_premium, 2, '.', '');

                        return response()->json([
                            'success' => 1,
                            'message'=>$type.' added successfully',
                            'annualPremium'=> $annual,
                            'monthly_premium'=> $monthly_premium,
                            'threeintsll_premium'=> $threeintsll_premium
                        ],200);
                    } else {
                        return response()->json(['success' => 0,'message'=>'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote')],401);
                    }
                } else {
                    return response()->json(['success' => 0,'message'=>'Values not found for role : ' . $Role->roles[0]->name],401);
                }
            } else {
                return response()->json(['success' => 0,'message'=>'You have exceeded maximum number of updates allowed'],401);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => 0,'message'=>$e->getMessage().'-'.$e->getLine()],401);
        }
    }

    public function getAnnualPremiumAPI($policyId){
        $policy = Policy::where('id',$policyId)->first(array('premium_freq','premium'));
        $premium = $policy->premium;

        if($policy){
            switch($policy->premium_freq){
                case 1:
                    $premium = ($policy->premium * 12) / 1.08;
                    break;
                case 2:
                    $premium = ($policy->premium) * 3;
                    break;
                case 3:
                    $premium = ($policy->premium);
                    break;
                default:
                    $premium = ($policy->premium);
            }
        }

        return $premium;

    }

    public function acceptNewRate(Request $request,$id){
        try{
            // dd($request->all());

            $policy = Policy::with('customer')->where('policyNumber',$id)->first(array('id','customer_id','policyNumber','first_premium','status'));

            $reratelog = PolicyPremiumReratingLog::where('ratings_id',$request->rateID)
            ->orderBy('id','DESC')
            ->first();

            if($reratelog == null)
                return Redirect::back()->with('error', 'No updated premium found for policy '.$id);

            $controller = new PolicyController();

            // if(isset($request->addDiscSurc) && $request->addDiscSurc == 1){
            //     // $dissurc = $controller->addDiscountSurcharge($request,$id);

            //     $request['policyId'] = $policy->id;
            //     $dissurc    = $controller->policyDiscountSurchargeAPI($request);

            //     if($dissurc->getData()->success == 0){
            //         return Redirect::back()->with('error', $dissurc->getData()->message);
            //     }
            // }else{
            //     $dissurc = null;
            // }

            //Update Customer with new info
            $profile = CustomerProfile::where('customer_id',$policy->customer_id)->first();
            if($profile == null)
                return Redirect::back()->with('error', 'Customer not found');

            // $profile->dob = Carbon::createFromFormat('d/m/Y', $reratelog->customer_dob)->format('Y-m-d');
            $profile->dob           = $reratelog->customer_dob;
            $profile->gender        = $reratelog->customer_gender;
            $profile->maritalstatus = $reratelog->customer_marital_status;
            $profile->save();

            $vehicle = array(
                'policy_id'       => $policy->id,
                'estimated_value' => $reratelog->sum_assured,
                'make'            => $reratelog->make,
                'model'           => $reratelog->model,
                'year'            => $reratelog->manufacturing_year,
                'is_imported'     => ($reratelog->japnese_import == 'Yes') ? 1 : 0,
                'claim_count'     => $reratelog->claim_count,
            );

            $updateVehicle = $controller->updateVehicleInfomation($vehicle);

            $premium = 0;

            if(isset($request->dis_sur_annual_premium)){
                $annual_premium = $request->dis_sur_annual_premium;
                switch($request->frequency){
                    case 1:
                        $policyCon     = new PolicyController();
                        $premium_vaule = $policyCon->getMonthlyPrem(3,$annual_premium);
                        $final_value   = round($premium_vaule,2);
                        $premium       = $final_value;
                        // $premium = $dissurc['premium']['monthly'];
                        break;
                    case 2:
                        $value = $annual_premium / 3;
                        $final_value = round($value,2);
                        $premium = $final_value;
                        // $premium = $dissurc['premium']['three'];
                        break;
                    case 3:
                        $premium = $annual_premium;
                        // $premium = $dissurc['premium']['yearly'];
                        break;
                }
            }else{
                $rerate_log = PolicyPremiumReratingLog::where('ratings_id', $request->rateID)
                    ->orderBy('id', 'DESC')
                    ->first();

                switch($request->frequency){
                    case 1:
                        $premium = $rerate_log->month_ins;
                        break;
                    case 2:
                        $premium = $rerate_log->three_ins;
                        break;
                    case 3:
                        $premium = $rerate_log->annual_ins;
                        break;
                }
            }

            // if(isset($request->addPayment) && $request->addPayment == 1) {
            //     return redirect()->route('admin.policy.rerate_billing', ['id' => $reratelog->id])->with('success', 'Added discount/surcharge');
            // }else {

                $data = [
                    "id"               => $policy->id,
                    "premium"          => $premium,
                    "premium_freq"     => $request->frequency,
                    "sum_assured"      => $reratelog->sum_assured,
                    "first_premium"    => isset($request->first_premium) ? $request->first_premium : $policy->first_premium,
                    "billingStartDate" => isset($request->billingDay) ? $request->billingDay : $policy->billingStartDate,
                ];

                $reratelog->status = 'Accepted';
                $reratelog->save();

                if ($policy->status == 0 && !isset($request->addPayment) && !isset($request->addPaymentCash) && !isset($request->addPaymentDpo)) {
                    $updateData = $this->updatenewRatePremium($data);
                }


                $createContract = null;
                if(isset($request->addPayment) && $request->addPayment == 1) {
                    $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    // $request['frequency'] = $request->new_frequency;
                    $createContract = $realpay->logRealpayPaymentForPolicyRenewal($request);
                    $updateData = $this->updatenewRatePremium($data);

                }

                if (isset($createContract) && $createContract->getData()->status != 200) {
                    if (isset($createContract->getData()->message)) {
                        return Redirect::route('admin.policy.edit', $policy->id)->with('error', $createContract->getData()->message);
                    } else {
                        return Redirect::route('admin.policy.edit', $policy->id)->with('error', 'Failed to add realpay contract.');
                    }
                }

                $cashPaymentEntry = null;
                $cashPaymentAlreadyLog = null;
                if(isset($request->addPaymentCash) && $request->addPaymentCash == 1 && !isset($request->cashPaymentDone)) {
                    $request['paymentFreq']        = $request->frequency;
                             $cashPaymentEntry     = $this->storeCashPaymentForRenewal($request);
                             $bankingData          = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
                             $bankingData->billing = 'cash';
                    $bankingData->save();
                    $data['billingStartDate'] = $request->paymentDate;
                          $updateData         = $this->updatenewRatePremium($data);

                } elseif (isset($request->cashPaymentDone) && $request->cashPaymentDone == 1 && isset($request->addPaymentCash) && $request->addPaymentCash == 1) {
                    $request['paymentFreq']                              = $request->frequency;
                             $cashPaymentAlreadyLog                      = PaymentTransaction::where('id', $request->selectPaymentData)->orderBy('id','desc')->first();
                             $cashPaymentAlreadyLog->amountAfterRerating = $premium;
                             $cashPaymentAlreadyLog->paymentAlreadyLog   = 1;
                    $cashPaymentAlreadyLog->save();
                    // $bankingData          = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
                    // $bankingData->billing = 'cash';
                    // $bankingData->save();
                    $data['billingStartDate'] = $cashPaymentAlreadyLog->paymentDate;
                    $updateData = $this->updatenewRatePremium($data);
                }

                if (isset($cashPaymentEntry) && $cashPaymentEntry->getData()->status != 200) {
                    return Redirect::route('admin.policy.edit', $policy->id)->with('error', 'Failed to add cash payment entry.');
                }


                if(isset($request->addPaymentDpo) && $request->addPaymentDpo == 1){
                    $prorata_premium = null;
                    if ($request->frequency == 1) {
                        $premium = isset($request->first_premiumDpo) ? $request->first_premiumDpo : $premium;

                        $date            = strtotime($request->first_collection_dateDpo);
                        $firstColDateDpo = \Carbon\Carbon::parse($date);

                        $now  = \Carbon\Carbon::now();
                        $days = $firstColDateDpo->diffInDays($now);

                        if ($days > 0) {
                            $prorata_premium = ($days/30.4375*$premium);
                            $prorata_premium = number_format($prorata_premium, 2, '.', ',');
                        }
                        elseif ($days == 0){
                            $prorata_premium = $premium;
                        }
                    }

                    // dd($prorata_premium);
                                   $smsMessaging               = new SmsMessaging;
                                   $rerateRequest              = new Request();
                    $rerateRequest['cellphone']                = $policy->customer->cellphone;
                    $rerateRequest['policy_id']                = $policy->id;
                    $rerateRequest['note']                     = null;
                    $rerateRequest['type']                     = 'addPaymentDpo';
                    $rerateRequest['amount']                   = isset($request->first_premiumDpo) && $request->frequency == 1 ? $request->first_premiumDpo : $premium;
                    $rerateRequest['frequency']                = isset($request->frequency) ? $request->frequency : null;
                    $rerateRequest['billingDay']               = isset($request->billingDayDpo) ? $request->billingDayDpo : null;
                    $rerateRequest['first_collection_date']    = isset($request->first_collection_dateDpo) ? $request->first_collection_dateDpo : null;
                    $rerateRequest['first_premium']            = isset($request->first_premiumDpo) ? $request->first_premiumDpo : null;
                    $rerateRequest['rerate_premium']           = isset($request->rerate_premiumDpo) ? $request->rerate_premiumDpo : null;
                    $rerateRequest['rerate_sum_assured']       = isset($reratelog->sum_assured) ? $reratelog->sum_assured : null;

                                   $res                        = $smsMessaging->sendPaymentUrlGraphite($rerateRequest)->getData();
                    if($res->status != 200)
                    {
                     return Redirect::route('admin.policy.edit', $policy->id)->withError('Problem with sending payment link for dpo on email and sms.');
                    }
                }

                if (isset($request->generatePaymentUrl)) {
                    return Redirect::route('admin.policy.policyView', [$policy->id,'#kt_generatePaymentUrl'])->with('success', 'Information updated successfully');
                } else {
                    return Redirect::route('admin.policy.edit', $policy->id)->with('success', 'Information updated successfully');
                }
            // }

        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage().' '.$ex->getLine().' '.$ex->getFile());
        }
    }

    public function updatenewRatePremium($data)
    {
        try {
            $policy = Policy::where('id',$data['id'])->first();
            $reratelogData = null;
            $motorcompData = null;
            if ($policy->product_id == 3) {
                if ($policy->quoteNumber != null) {
                    $motorcompData= MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();
                    if (isset($motorcompData)) {
                        $reratelogData = PolicyPremiumReratingLog::where('ratings_id',$motorcompData->ratings_id)->orderBy('id','desc')->first();
                    }
                }
            }

            $updatePolicyInfo = Policy::updateInfo($data);

            $reratelogData->payment_status = 'Completed';
            $reratelogData->status = 'Completed';
            $reratelogData->save();

        } catch (\Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage().' '.$ex->getLine().' '.$ex->getFile());

        }

    }

    public function reratePremiumPayDetails(Request $request)
    {

    }

    public function updateDevices(Request $request)
    {
        if ($request->devices != NULL) {
            foreach ($request->devices as $key => $device) {
                $device_id = htmlspecialchars(strip_tags($device['device_id']));
                if ($device_id != NULL) {
                    $policyCellPhone = PolicyCellPhone::where('id', $device_id)->first();
                } else {
                    $policyCellPhone = new PolicyCellPhone();
                    $policyCellPhone->policy_id = $request->input('policy_id');
                    $policyCellPhone->customer_id = $request->input('customer_id');
                }
                if (isset($device['device_type']) && htmlspecialchars(strip_tags($device['device_type'])) != NULL)
                    $policyCellPhone->device_type = htmlspecialchars(strip_tags($device['device_type']));
                if (isset($device['imei']) && htmlspecialchars(strip_tags($device['imei'])) != NULL)
                    $policyCellPhone->imei = htmlspecialchars(strip_tags($device['imei']));
                if (isset($device['phone_value']) && htmlspecialchars(strip_tags($device['phone_value'])) != NULL)
                    $policyCellPhone->phone_value = htmlspecialchars(strip_tags($device['phone_value']));
                if (isset($device['cell_phone_make']) && htmlspecialchars(strip_tags($device['cell_phone_make'])) != NULL) {
                    $cell_phone_make = htmlspecialchars(strip_tags($device['cell_phone_make']));
                    $make = DeviceMakeModel::where('id', $cell_phone_make)->first(array('name'));
                    if ($make != NULL)
                        $policyCellPhone->cell_phone_make = $make->name;
                }
                if (isset($device['cell_phone_model']) && htmlspecialchars(strip_tags($device['cell_phone_model'])) != NULL) {
                    $cell_phone_model = htmlspecialchars(strip_tags($device['cell_phone_model']));
                    $model = DeviceMakeModel::where('id', $cell_phone_model)->first(array('name'));
                    if ($model != NULL)
                        $policyCellPhone->cell_phone_model = $model->name;
                }

                if (isset($device['cell_phone_front']) && $device['cell_phone_front'] != NULL) {
                    $file = $device['cell_phone_front'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/front' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_front = $filePath;
                    }
                }
                if (isset($device['cell_phone_back']) && $device['cell_phone_back'] != NULL) {
                    $file = $device['cell_phone_back'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/back' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_back = $filePath;
                    }
                }
                if (isset($device['cell_phone_left']) && $device['cell_phone_left'] != NULL) {
                    $file = $device['cell_phone_left'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/left' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_left = $filePath;
                    }
                }
                if (isset($device['cell_phone_right']) && $device['cell_phone_right'] != NULL) {
                    $file = $device['cell_phone_right'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/right' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_right = $filePath;
                    }
                }
                if (isset($device['cell_phone_top']) && $device['cell_phone_top'] != NULL) {
                    $file = $device['cell_phone_top'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/top' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_top = $filePath;
                    }
                }
                if (isset($device['cell_phone_bottom']) && $device['cell_phone_bottom'] != NULL) {
                    $file = $device['cell_phone_bottom'];
                    $result = $this->isImageValid($file);
                    if ($result == true) {
                        $name = $file->getClientOriginalName();
                        $filePath = 'device/' . $policyCellPhone->policy_id . '/' . 'Cellphone/' . $policyCellPhone->customer_id . '/bottom' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $policyCellPhone->cell_phone_bottom = $filePath;
                    }
                }
                $policyCellPhone->save();
            }
            return Redirect::back()->with('success', 'Devices Updated Successfully');
        }
    }

    public function isImageValid($image)
    {
        try {
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
            } elseif (auth::user()->hasPermissionTo('image-Upload Without Validation')) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkCompliance($customer_id)
    {
        if ($customer_id) {
            $kyc = KYC::where('customer_id', $customer_id)->first();
            if ($kyc->driving_license != null && $kyc->proof_residence != null && $kyc->proof_income != null && ($kyc->omang != null || $kyc->passport != null))
                return 1;
            else
                return 0;
        }
    }

    public function addDiscountSurchargePolicy($id)
    {
        $policy = Policy::where('id',$id)->first();
        $functionality ='Reinstate';
        return view('admin.policy.addDiscountSurcharge',compact('policy','functionality'));
    }


    public function renewPolicy($id)
    {
        try{
            $data = DB::table('policies')
                ->leftJoin('customer','customer.id','=','policies.customer_id')
                ->leftJoin('motor_comp_quotes','motor_comp_quotes.quoteNumber','=','policies.quoteNumber')
                ->leftJoin('customer_profile','customer_profile.customer_id','=','customer.id')
                ->leftJoin('vehicle','vehicle.policy_id','=','policies.id')
                ->leftJoin('policy_renewals', 'policy_renewals.policyNumber', 'policies.policyNumber')
                ->where('policies.id',$id)
                ->first();

                $policy_premium = Policy::where('id',$id)->first('premium');

                $data->priorAccidents = Claim::where('policy_id',$id)->count()+$data->priorAccidents;

                $premium = PolicyDiscountSurcharge::where('policy_id',$id)->orderBy('id','desc')->first('new_value');

                $data->policy_id = $id;
            $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));

            return view('admin.policy.renewPolicy',compact('data','agents','premium','policy_premium'));
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function policyRenewalOperations(Request $request)
    {
        try{
            // dd($request->all());
            $policy = Policy::where('id',$request->policyId)->where('status',1)->where('product_id',$request->productId)->first();
            // dd($policy);
            if(isset($policy)){
                $policyRenewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
                if(isset($policy) && !isset($policyRenewal)){

                    $count = Claim::where('policy_id',$policy->id)->count();
                    $customer = Customer::where('id',$policy->customer_id)->first(['customer_category']);

                    if($policy->policyActivatedDate != null) {
                        $expiryDate = Carbon::parse($policy->policyActivatedDate)->addYear(1)->format('Y-m-d');
                    }else{
                        $trx = PaymentTransaction::where('policyNumber',$policy->policyNumber)->first(array('paymentDate'));
                        if($trx && $trx->paymentDate != null){
                            $expiryDate = Carbon::parse($trx->paymentDate)->addYear(1)->format('Y-m-d');
                        }else{
                            if($policy->billingStartDate)
                                $expiryDate = Carbon::parse($policy->billingStartDate)->addYear(1)->format('Y-m-d');
                            else
                                $expiryDate = new Carbon(Carbon::now()->format('Y-m-d'));
                        }
                    }

                    $datetime1 = new Carbon(Carbon::now()->format('Y-m-d'));
                    $datetime2 = new Carbon($expiryDate);
                    $interval = $datetime1->diff($datetime2);
                    $days = (int)$interval->format("%r%a");

                    $currentDate = date('m/d/Y', strtotime($expiryDate));
                    $currentDate = date('Y-m-d',strtotime($currentDate));

                    $startDate = Carbon::now()->format('Y-m-d');
                    $endDate = Carbon::parse($startDate)->addDays(90)->format('Y-m-d');

                    $diffDays =  \Carbon\Carbon::createFromTimeStamp(strtotime($currentDate))->diffInDays();

                    if ($diffDays <= 90 ){

                        switch($policy->premium_freq){
                            case 1:
                                $annual = ($policy->premium * 12) / 1.08;
                                break;
                            case 2:
                                $annual = ($policy->premium) * 3;
                                break;
                            case 3:
                                $annual = ($policy->premium);
                                break;
                            default:
                                $annual = ($policy->premium);
                        }

                        $qd = MotorComprehensiveQuotes::where('quoteNumber',$policy->quoteNumber)->first();


                        $transactions = PaymentTransaction::where('policyNumber',$policy->policyNumber)
                            ->orderBy('id','desc')
                            ->first(['paymentMethod']);

                        $add = new PolicyRenewal();
                        $add->policy_id = $policy->id;
                        $add->policyNumber = $policy->policyNumber;
                        $add->expiry_date = $expiryDate;
                        $add->sum_assured = $policy->sum_assured;
                        $add->sms_sent = NULL;
                        $add->email_sent = NULL;
                        $add->claim_count = $count;
                        $add->paymentFrequency = $policy->premium_freq;
                        $add->paymentMethod = ($transactions != null) ? $transactions->paymentMethod : null;
                        $add->days_remaining_to_expire = $days;
                        $add->old_premium = round($annual,2);
                        if($add->save()){
                            return redirect()->back()->with('success', 'Policy has been moved to renew table successfully.');
                        }else{
                            return redirect()->back()->with('error', 'Failed. Please try again.');
                        }
                    }else{
                        return redirect()->back()->with('error', 'This policy is not available for renew.');
                    }
                }else{
                    return redirect()->back()->with('error', 'Policy already exist at renewal table.');
                }
            }else{
                return redirect()->back()->with('error', 'Policy not found.');
            }

            $data = DB::select(DB::raw('select max(id) as ref_id,policyNumber from payment_transactions
                                        where policyNumber in (select policyNumber from policy_renewals)
                                        and paymentMethod = "RealPay"
                                        group by policyNumber;'));

            if(count($data) > 0){
                foreach($data as $key=>$d){
                    $payment = PaymentTransaction::where('id',$d->ref_id)->first(array('paymentDate','paymentMethod','status'));
                   # $instalment = RealpayContractInstallments::where('clientNumber',$policy->policyNumber)->orderBy('id','desc')->first();
                    $renewal = PolicyRenewal::where('policyNumber',$d->policyNumber)->orderBy('id', 'desc')->first();


                    $renewal->paymentMethod = $payment->paymentMethod;
                    $renewal->lastPaymentstatus = $payment->status;
                    $renewal->lastPaymentDate = $payment->paymentDate;
                    $renewal->save();


                }
            }


            $ref = DB::select(DB::raw('select max(id) as ref_id from realpay_contract_installments
                    where clientNumber in (select policyNumber from policy_renewals where paymentFrequency = 3 and paymentMethod = "RealPay")
                    group by clientNumber;'));

            if(count($ref) > 0){
                foreach($ref as $key=>$r){

                    $cl = RealpayContractInstallments::where('id',$r->ref_id)->first();
                    if($cl){
                        $renewal = PolicyRenewal::where('policyNumber',$cl->clientNumber)->orderBy('id', 'desc')->first();
                        $renewal->lastInstalmentSequence = $cl->InstalmentSequence;
                        $renewal->lastInstalmentStatus = $cl->InstalmentStatus;
                        $renewal->save();
                    }
                }
            }

            return redirect('admin/renewalPolicy');
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function LedgerInvoiceDelete(Request $request)
    {
        try{
            $policy = Policy::where('id', $request->policyId)->first();
            $ledger = Ledger::where('id', $request->ledgerInvoiceId)->where('trans_type', 'Invoice')->first();
            $invoice_no = $ledger->invoice_no;
            $banking_id = $ledger->banking_id;
            $balance = Ledger::where('policy_id', $request->policyId)->orderBy('id', 'DESC')->first(array('balance'));
            if ($balance != null) {
                $balance = $balance->balance;
            } else {
                $balance = 0;
            }
            $created_date = Carbon::now()->format('Y-m-d');
            $data = array();
            $record = array();
            $subData = array();
            $subRecord = array();

            //-------------------------------PREMIUM--------------------------------------
            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = NULL;
            $record['policy_id'] = $policy->id;
            $record['claim_id'] = NULL;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = NULL;
            $record['accounting_date'] = Carbon::parse($created_date);
            $record['trans_type'] = 'Reverse Invoice Premium';
            $record['amount_type'] = NULL;
            $record['trans_ref'] = NULL;
            $record['orig_trans'] = NULL;
            $record['unallocated'] = NULL;
            $record['system_date'] = Carbon::parse($created_date);
            $record['trans_sub_type'] = NULL;
            $record['eff_date'] = Carbon::parse($created_date);
            $record['invoice_file'] = NULL;
            $record['invoice_date'] = NULL;
            $record['invoice_no'] = NULL;
            $record['invoice_amount'] = NULL;
            $record['premium'] = $ledger->premium;
            $record['other_charges'] = NULL;
            $record['due_amount'] = NULL;
            $record['pmts_adjust'] = NULL;
            $record['due_date'] = NULL;
            $record['status'] = 'Reversed';
            $record['debit'] = NULL;

            $amt = str_replace(',', '',number_format(((float)$ledger->premium - (float)$policy->vat), 2));
            $record['credit'] = str_replace(',', '',$amt);
            $balance = number_format(((float)str_replace(',', '',$balance) + (float)str_replace(',', '',$amt)), 2);
            $record['balance'] = str_replace(',', '',$balance);

            $data[] = $record;

            //----SUB-LEDGER
            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = NULL;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['claim_id'] = NULL;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'Insurance Sales A/C';
            $subRecord['accounting_date'] = Carbon::parse($created_date);
            $subRecord['trans_type'] = 'Insurance Premium';
            $subRecord['trans_ref'] = NULL;
            $subRecord['system_date'] = Carbon::parse($created_date);
            $subRecord['debit'] = $amt;
            $subRecord['credit'] = NULL;

            $subData[] = $subRecord;

            //-------------------------------PREMIUM--------------------------------------
            //-------------------------------VAT--------------------------------------

            $record = array();

            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = NULL;
            $record['policy_id'] = $policy->id;
            $record['claim_id'] = NULL;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = NULL;
            $record['accounting_date'] = Carbon::parse($created_date);
            $record['trans_type'] = 'Reverse Invoice VAT';
            $record['amount_type'] = NULL;
            $record['trans_ref'] = NULL;
            $record['orig_trans'] = NULL;
            $record['unallocated'] = NULL;
            $record['system_date'] = Carbon::parse($created_date);
            $record['trans_sub_type'] = NULL;
            $record['eff_date'] = Carbon::parse($created_date);
            $record['invoice_file'] = NULL;
            $record['invoice_date'] = NULL;
            $record['invoice_no'] = NULL;
            $record['invoice_amount'] = NULL;
            $record['premium'] = $ledger->premium;
            $record['other_charges'] = NULL;
            $record['due_amount'] = NULL;
            $record['pmts_adjust'] = NULL;
            $record['due_date'] = NULL;
            $record['status'] = 'Reversed';
            $record['debit'] = NULL;

            $amt = floatval($policy->vat);

            $record['credit'] = str_replace(',', '',$amt);
            $balance = str_replace(',', '',number_format(((float)str_replace(',', '',$balance) + (float)str_replace(',', '',$amt)), 2));
            $record['balance'] = str_replace(',', '',$balance);

            $data[] = $record;

            //----SUB-LEDGER

            $subRecord = array();

            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = NULL;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['claim_id'] = NULL;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'VAT Control A/C';
            $subRecord['accounting_date'] = Carbon::parse($created_date);
            $subRecord['trans_type'] = 'VAT on Insurance Premium';
            $subRecord['trans_ref'] = NULL;
            $subRecord['system_date'] = Carbon::parse($created_date);
            $subRecord['debit'] = $amt;
            $subRecord['credit'] = NULL;

            $subData[] = $subRecord;

            //-------------------------------VAT--------------------------------------
            //-------------------------------INVOICE--------------------------------------

            $record = array();
            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = NULL;
            $record['policy_id'] = $policy->id;
            $record['claim_id'] = NULL;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = NULL;
            $record['accounting_date'] = Carbon::parse($created_date);
            $record['trans_type'] = 'Reverse Invoice';
            $record['amount_type'] = NULL;
            $record['trans_ref'] = NULL;
            $record['orig_trans'] = NULL;
            $record['unallocated'] = NULL;
            $record['system_date'] = Carbon::parse($created_date);
            $record['trans_sub_type'] = NULL;
            $record['eff_date'] = Carbon::parse($created_date);
            $record['invoice_file'] = NULL;
            $record['invoice_date'] = Carbon::parse($created_date);
            $record['invoice_no'] = $invoice_no;
            $record['invoice_amount'] = $ledger->premium;
            $record['premium'] = $ledger->premium;
            $record['other_charges'] = NULL;
            $record['due_amount'] = $ledger->premium;
            $record['pmts_adjust'] = NULL;
            $record['due_date'] = NULL;
            $record['status'] = 'Reversed';
            $record['debit'] = NULL;

            $amt = number_format(((float)str_replace(',', '',$ledger->premium) - (float)str_replace(',', '',$policy->vat)), 2);

            $record['credit'] = $ledger->premium;
            $record['balance'] = str_replace(',', '',$balance);

            $data[] = $record;

            //----SUB-LEDGER

            $subRecord = array();
            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = NULL;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['claim_id'] = NULL;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'Accounts Receivable A/C';
            $subRecord['accounting_date'] = Carbon::parse($created_date);
            $subRecord['trans_type'] = 'Accounts Receivable';
            $subRecord['trans_ref'] = NULL;
            $subRecord['system_date'] = Carbon::parse($created_date);
            $subRecord['debit'] = NULL;
            $subRecord['credit'] = $ledger->premium;

            $subData[] = $subRecord;

            $subData[0]['trans_ref'] = $invoice_no;
            $subData[1]['trans_ref'] = $invoice_no;
            $subData[2]['trans_ref'] = $invoice_no;

            //-------------------------------INVOICE-------------------------------------

            Ledger::insert($data);
            SubLedger::insert($subData);

            $ledger->action_by = auth()->user()->id;
            $ledger->action_at = Carbon::now();
            $ledger->status = 'Reversed';
            $ledger->save();

            return redirect()->back()->with('success', 'Invoice Reversed Successfully');
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function TransactionLogDelete(Request $request)
    {
        try{
            $policy = Policy::where('id', $request->policyId)->first();
            $transaction = PaymentTransaction::where('id', $request->transactionLogId)->first();
            $ledger = Ledger::where('trans_ref', $transaction->referenceNumber)->first();
            $banking_id = $ledger->banking_id;
            $balance = Ledger::where('policy_id', $request->policyId)->orderBy('id', 'DESC')->first(array('balance'));
            if ($balance != null) {
                $balance = $balance->balance;
            } else {
                $balance = 0;
            }
            $date = Carbon::now()->format('Y-m-d');
            $created_date = Carbon::now()->format('Y-m-d');
            $data = array();
            $record = array();
            $subData = array();
            $subRecord = array();

            $record['customer_id'] = $policy->customer_id;
            $record['account_id'] = NULL;
            $record['policy_id'] = $policy->id;
            $record['claim_id'] = NULL;
            $record['banking_id'] = $banking_id;
            $record['account_name'] = NULL;
            $record['accounting_date'] = $date;
            $record['trans_type'] = 'Reverse Payment';
            $record['amount_type'] = NULL;
            $record['trans_ref'] = $transaction->referenceNumber;
            $record['orig_trans'] = $transaction->referenceNumber;
            $record['unallocated'] = NULL;
            $record['system_date'] = $date;
            $record['trans_sub_type'] = NULL;
            $record['eff_date'] = $date;
            $record['invoice_file'] = NULL;
            $record['invoice_date'] = NULL;
            $record['invoice_no'] = NULL;
            $record['invoice_amount'] = NULL;
            $record['premium'] = $ledger->premium;
            $record['other_charges'] = NULL;
            $record['due_amount'] = NULL;
            $record['pmts_adjust'] = NULL;
            $record['due_date'] = NULL;
            $record['status'] = 'Reversed';
            $record['credit'] = NULL;


            $transaction->amount = str_replace(',', '',(int)$transaction->amount);
            $record['debit'] = $transaction->amount;
            if($balance < 0)
            {
                $record['balance'] = str_replace(',', '',number_format((abs($balance) + $transaction->amount), 2));
                $balance = str_replace(',', '',number_format((abs($balance) + $transaction->amount), 2));
            } else {
                $record['balance'] = str_replace(',', '',number_format(($balance + $transaction->amount), 2));
                $balance = str_replace(',', '',number_format(($balance + $transaction->amount), 2));
            }

            $data[] = $record;

            //----SUB-LEDGER
            $subRecord = array();
            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = NULL;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['claim_id'] = NULL;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'Accounts Receivable A/C';
            $subRecord['accounting_date'] = Carbon::parse($created_date);
            $subRecord['trans_type'] = 'Reverse Accounts Receivable';
            $subRecord['trans_ref'] = $transaction->referenceNumber;
            $subRecord['system_date'] = Carbon::parse($created_date);
            $subRecord['debit'] = $transaction->amount;
            $subRecord['credit'] = NULL;

            $subData[] = $subRecord;
            //---------------------------------//
            $subRecord = array();

            $subRecord['customer_id'] = $policy->customer_id;
            $subRecord['account_id'] = NULL;
            $subRecord['policy_id'] = $policy->id;
            $subRecord['claim_id'] = NULL;
            $subRecord['banking_id'] = $banking_id;
            $subRecord['account_name'] = 'Bank A/C';
            $subRecord['accounting_date'] = Carbon::parse($created_date);
            $subRecord['trans_type'] = 'Reverse Cash Received';
            $subRecord['trans_ref'] = $transaction->referenceNumber;
            $subRecord['system_date'] = Carbon::parse($created_date);
            $subRecord['debit'] = NULL;
            $subRecord['credit'] = $transaction->amount;

            $subData[] = $subRecord;

            //----SUB-LEDGER

            Ledger::insert($data);
            SubLedger::insert($subData);

            $transaction->CompanyRef = 'Reversed';
            $transaction->save();

            $ledger->action_by = auth()->user()->id;
            $ledger->action_at = Carbon::now();
            $ledger->status = 'Reversed';
            $ledger->save();

            return redirect()->back()->with('success', 'Transaction Reversed Successfully');
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * method to change policy status
     *param: policy id ($id)
     * @return View
     */
    public function action($id, $status, $billing_method=NULL)
    {
        $policy = Policy::where('id', $id)->first();
        $user   = Customer::where('id', $policy->customer_id)->first();
        if($billing_method !='DPO'){// code edited by rashmi on 26 nov
            if ($status == 1) {
                $transaction = Transaction::where('policyNumber', $policy->policyNumber)->where('status', 'SUCCESS')->first('status');
                if ($transaction && $transaction->status != 'SUCCESS') {
                    activity('Policy Status')
                        ->performedOn($policy)
                        ->log('Policy cannot be activated as Payment Status is Failed');  // code edited by rashmi on 26 nov
                    return redirect()->back()->with('error', 'Policy cannot be activated as Payment Status is Failed');
                }
            }
        }
        $policy->status = $status;
        $policy->policyActivatedDate = Carbon::now();
        $product = Product::where('id', $policy->product_id)->first(array('has_activation_code'));
        if ($status == 1 && $product->has_activation_code == 1) {
            $activation = Activation::where('activation_code', $policy->activation_code)->first(array('trial_periods', 'trial_coverage', 'activation_code'));
            $policy->trial_coverage = $activation->trial_coverage;
            $policy->serial_code = $activation->activation_code;
            $policy->trial_period = Carbon::now()->addDays($activation->trial_periods)->format('Y-m-d');
            $banking = CustomerBanking::where('policy_id', $id)->first();
            if (empty($banking)) {
                $banking = new CustomerBanking();
                $banking->customer_id = $policy->customer_id;
                $banking->policy_id = $id;
                $banking->billing = $transaction->transactionType;
                $banking->billingCell = $user->cellphone;
                $banking->save();
            }
            $pos = strpos($policy->billingStartDate, '/');
            if ($pos !== false) {
                $banking->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('Y-m-d');
            } else {
                $banking->billingStartDate = Carbon::parse($policy->billingStartDate)->format('Y-m-d');
            }
            $banking->save();
        }
        $saved = $policy->save();

        if (Auth::check()) {
            if ($status == 2) {
                activity('Policy Status')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy Status Updated to : CANCELLED');
            } elseif ($status == 0) {
                activity('Policy Status')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy Status Updated to : DEACTIVATED');
            } else {
                activity('Policy Status')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy Status Updated to : ACTIVATED');
            }
        }
        if ($status == 2) {
            Helper::ledgerStore($user->id, 'POLICY', $policy->id, $policy->product_id, 'CANCEL');
        }
        return redirect()->back()->with('success', 'Policy Status Updated');
    }

    public function actionToActivate($id, $status)
    {
        $policy = Policy::where('id', $id)->first();
        $currStatus = '';
        if ($policy->status == 0) {
            $currStatus = 'In-active';
        }
        if ($policy->status == 2) {
            $currStatus = 'Cancelled';
        }

        $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();

        if ($transctionsRow == null)
            return 'Reference number not found';

        $policy->status = $status;
        $saved = $policy->save();
        $referenceNumber = $transctionsRow->referenceNumber;
        $vcs = new PaymentController;
        $vcs->unsuspendTransactionOnVCS($referenceNumber);
        $update = $this->updatePolicyDates($policy->policyNumber, 1);
        if (Auth::check()) {
            activity('Policy Status')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Policy Status Updated: ' . $currStatus . ' to Active');
        }

        return 1;
    }



    /**
     * method to return modal body for confirm-delete.
     *
     * @return json
     */
    public function getModalDelete(Request $request)
    {
        $check = Product::where('region_id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves
        if ($check) {
            // Prepare the error message
            $body = 'Policy Assigned to Products. Cannot delete this Region';
            return response()->json(['status' => 'error', 'body' => $body]);
        } else {
            $body = 'Are you sure you want to delete the Region ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }

    public function getModalAttachmentDelete(Request $request)
    {
        $check = PolicyAttachments::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves
        if ($check) {
            $body = 'Are you sure you want to delete the Attachment ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }

    public function destroy_attachment($id)
    {
        try {
            if(PolicyAttachments::where('id', $id)->exists())
            {
                PolicyAttachments::where('id', $id)->delete();
                return redirect()->back()->with('success', 'Policy Attachments Deleted Successfully');
            }
            else{
                return redirect()->back()->with('error', 'Something Went Wrong');
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    public function getModalInvoiceDelete(Request $request)
    {
        $check = Ledger::where('id', $request->get('id'))->count();
        if ($check) {
            $body = 'Are you sure you want to delete the Invoice ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }
    public function destroyInvoice($id)
    {
        try {
            if(Ledger::where('id', $id)->exists())
            {
                Ledger::where('id', $id)->delete();
                return redirect()->back()->with('success', 'Invoice Deleted Successfully');
            }
            else{
                return redirect()->back()->with('error', 'Something Went Wrong');
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }


    public function getModalsmsEmailLogDelete(Request $request)
    {

        $check = SMSEmailLogs::where('id', $request->get('id'))->count();
        // Check if we are not trying to delete ourselves
        if ($check) {
            $body = 'Are you sure you want to delete the SMS/Email Log ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }

    public function destroy_sms_email_log($id)
    {
        try {
            if(SMSEmailLogs::where('id', $id)->exists())
            {
                SMSEmailLogs::where('id', $id)->delete();
                return redirect()->back()->with('success', 'SMS/Email Log Deleted Successfully');
            }
            else{
                return redirect()->back()->with('error', 'Something Went Wrong');
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }

    public function getModaldiscountSurchargePolicyDelete(Request $request)
    {
        $check = PolicyDiscountSurcharge::where('id', $request->get('id'))->count();
        if ($check) {
            $body = 'Are you sure you want to delete  Policy Discount Surcharge ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }

    public function destroy_discount_surcharge_policy($id)
    {
        try {
            if(PolicyDiscountSurcharge::where('id', $id)->exists())
            {
                PolicyDiscountSurcharge::where('id', $id)->delete();
                return redirect()->back()->with('success', 'Policy Discount Surcharge Deleted Successfully');
            }
            else{
                return redirect()->back()->with('error', 'Something Went Wrong');
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Something Went Wrong');
        }
    }
    /**
     *deletes specific policy
     *param: policy id ($id)
     * @return user listing page
     */
    public function destroy($id)
    {
        try {
            $region = Region::where('id', $id)->delete();
            return Redirect::route('admin.region.index')->with('success', 'Region Deleted Successfully');
        } catch (TeacherNotFoundException $e) {
            return Redirect::route('admin.region.index')->with('error', 'Something Went Wrong');
        }
    }

    public function getProductFactors(Request $request)
    {
        if ($request->get('omang') == null && $request->get('passport') == null) {
            return response()->json(['status' => 'noCustomer']);
        }
        if ($request->get('omang') != null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') == null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') != null && $request->get('passport') == null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
        } else {
            $profile = null;
        }

        if ($profile != null) {
            $check = Policy::where('customer_id', $profile->customer_id)->where('product_id', $request->get('id'))->first(array('has_vehicle'));
            if ($check != null && $check->has_vehicle == 0) {
                return response()->json(['status' => 'existing']);
            }
        }
        $check = FactorMain::with('value')->where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'type'));
        // Check if we are not trying to delete ourselves
        $product = Product::where('id', $request->get('id'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'has_activation_code', 'is_motor_items', 'preinspection', 'kyc_customer', 'sum_insured', 'has_subApplicant'));
        if ($product->premium_type_id == 11) {
            $plans = Productplan::where('product_id', $request->get('id'))->where('status', 1)->get(array('id', 'name', 'premium', 'sum_assured'))->toArray();
        } else {
            $plans = null;
        }

        $coverage = ProductCoverage::where('product_id', $request->get('id'))->get(array('coverage_id', 'name'));
        return response()->json(['status' => 'success', 'factors' => $check, 'product' => $product, 'coverage' => $coverage, 'plans' => $plans]);
    }

    /*
     * fetching all the active products
     * return: json
     */
    public function getProducts()
    {
        $check = Product::where('status', 1)->get(array('id', 'name'));
        // Check if we are not trying to delete ourselves
        if ($check->count() == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            return response()->json(['status' => 'success', 'products' => $check]);
        }
    }

    /*
     * method to calculate premium value
     * return: json
     */
    public function calculatePremium(Request $request)
    {
        $product = Product::where('id', $request->get('product_id'))->first(array('formula'));
        if ($product->formula == null) {
            // Prepare the error message
            return response()->json(['status' => 'error']);
        } else {
            $selectedFactorsValues = $request->get('factors');
            //Get all factor main Ids from formula in output
            preg_match_all('~_(.*?)]]~', $product->formula, $output);
            $parameter = array();
            $replace_parameter = array();
            $factors = FactorMain::whereIn('id', $output[1])->get(array('id', 'name', 'type'));
            foreach ($factors as $factor) {
                $factor->name = str_replace(' ', '', $factor->name);
                $factor->name = str_replace('?', '', $factor->name);
                $msg_feild = '[[' . $factor->name . '_' . $factor->id . ']]';
                array_push($parameter, $msg_feild);
                if ($factor->type == 'Input Field') {
                    $inputs = $request->get('input');
                    foreach ($inputs as $input) {
                        $value = explode('_', $input);
                        if ($value[1] == $factor->id) {
                            $msg_replace_feilds = $value[2];
                        }
                    }
                } else {
                    $msg_replace_feilds = FactorSubType::where('main_id', $factor->id)->whereIn('id', explode(',', $selectedFactorsValues))->sum('factor');
                }
                array_push($replace_parameter, (int) $msg_replace_feilds);
            }
            $math = str_replace($parameter, $replace_parameter, $product->formula);
            eval('$result = (' . $math . ');');
            return response()->json(['response' => '1', 'premium' => $result]);
        }
    }

    /*
     * save policy Yes
     */
    public function savePolicyYes(Request $request)
    {
        $user = new Customer();
        $user->firstName = $request['fname'];
        $user->lastName = $request->get('lname');
        $user->email = $request->get('email');
        $user->cellphone = $request->get('cellphone');
        $user->save();
        $profile = new CustomerProfile();
        $profile->customer_id = $user->id;
        $profile->gender = $request->get('gender');
        $profile->address = $request->get('address');
        $profile->omang = $request->get('omang');
        $profile->passport = $request->get('passport');
        $profile->dob = Carbon::parse($request->get('dob'));
        $profile->save();
        //Latestid for Policy Number
        $latest = Policy::latest('policyNumber')->orderBy('id', 'DESC')->first(array('policyNumber', 'storeID', 'product_id', 'plan_id'));
        if ($latest == null) {
            $latest = collect();
            $latest->id = 0;
        }
        $policy = new Policy();
        $policy->customer_id = $user->id;
        $policy->product_id = $request['product'];
        $policy->premium = $request->get('premium');
        $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad((substr($latest->policyNumber, -6) + 1), 6, '0', STR_PAD_LEFT);
        $policy->status = 1;

        $mains = FactorMain::where('product_id', $request->get('"product'))->where('status', 1)->get(array('id', 'name'));
        foreach ($mains as $main) {
            if (is_array($request->get('factor_' . $main->id))) {
                foreach ($request->get('factor_' . $main->id) as $key => $value_id) {
                    $factor = new PolicyFactor();
                    $factor->policy_id = $policy->id;
                    $factor->factor_main_id = $main->id;
                    $factor->name = $main->name;
                    $factor->type = $main->type;
                    $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                    $factor->factor_value_id = $value_id;
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $saved = $factor->save();
                }
            } else {
                $factor = new PolicyFactor();
                $factor->policy_id = $policy->id;
                $factor->factor_main_id = $main->id;
                $factor->name = $main->name;
                $factor->type = $main->type;
                $value = FactorSubType::where('id', $request->get('factor_' . $main->id))->first(array('name', 'factor'));
                if ($value == null) {
                    $factor->value_name = $request->get('factor_' . $main->id);
                } else {
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $factor->factor_value_id = $request->get('factor_' . $main->id);
                }
                $saved = $factor->save();
            }
        }
        $relation = $request->relation;
        $memberLName = $request->memberLName;
        $memberDOB = $request->memberDOB;
        $memberSuminsured = $request->memberSuminsured;
        foreach ($request->get('memberFName') as $key => $member) {
            if ($member != null) {
                $m = new PolicyMember();
                $m->policy_id = $policy->id;
                $m->relation = $relation[$key];
                $m->first_name = $member[$key];
                $m->last_name = $memberLName[$key];
                $m->dob = Carbon::parse($memberDOB[$key]);
                $m->gender = $memberSuminsured[$key];
                $m->save();
            }
        }
        $banking = new CustomerBanking();
        $banking->customer_id = $user->id;
        $banking->policy_id = $policy->id;
        $banking->billing = $request->get('billing');
        $banking->billingCell = $request->get('billingCell');
        $banking->bankName = $request->get('bankName');
        $banking->branchCode = $request->get('branchCode');
        $banking->accountType = $request->get('bankAccountType');
        $banking->accountNumber = $request->get('accountNumber');
        $saved = $banking->save();
        if ($saved) {
            activity('Policy ')
                ->performedOn($policy)
                ->causedBy(User::where('id', auth()->user()->id)->first())
                ->log('Policy Created Successfully');

            // Redirect to the home page with success menu
            return response()->json(array('status' => 'success', 'message' => 'Policy Created Successfully'));
        } else {
            return response()->json(array('status' => 'success', 'message' => 'Something Went Wrong'));
        }
    }

    /*
     * save policy No
     */
    public function savePolicyNo(Request $request)
    {
        $policyLeadsFactorsArray = array_merge($request->factor_1, $request->factor_2, $request->factor_1, $request->factor_input);
        $policy_leads = new PolicyLeads();
        $policy_leads->fname = $request['fname'];
        $policy_leads->lname = $request->lname;
        $policy_leads->email = $request->email;
        $policy_leads->gender = $request->gender;
        $policy_leads->maritalstatus = $request->maritalstatus;
        $policy_leads->dob = Carbon::parse($request->dob);
        $policy_leads->cellphone = $request->cellphone;
        $policy_leads->omang = $request->omang;
        $policy_leads->passport = $request->passport;
        $policy_leads->reason_policy = $request->reason_policy;
        $policy_leads->save();
        foreach ($policyLeadsFactorsArray as $item) {
            $policyLeadsFactor = new PolicyLeadsFactor();
            $policyLeadsFactor->policy_leads_id = $policy_leads->id;
            $policyLeadsFactor->factor_main_id = $item;
            $policyLeadsFactor->save();
        }
        return response()->json(array('status' => 'success'));
    }

    /*
     * deletes policy motor items
     * param: policy motor item id
     * return : json
     */
    public function deletePolicyMotorItem($policyMotorItem)
    {
        PolicyMotorItems::find($policyMotorItem)->delete();
        activity('Motor Item Delete')
            ->performedOn($policyMotorItem)
            ->causedBy(User::where('id', auth()->user()->id)->first())
            ->log('Motor Policy Item Deleted');
        return redirect()->back()->with('success', 'Motor Policy Item Deleted Successfully');
    }

    /*
     * retrieves claim data respect to policy
     * param: policy ID
     * return JSON
     */
    public function getclaimsdata($id)
    {
        $claims = Claim::where('policy_id', $id)->get(array('id', 'policy_id', 'claim_number', 'claim_type', 'status', 'created_at','created_by'));
        return DataTables::of($claims)
            ->addColumn('claim_handler', function ($claims) {
                $claim_handler = 'N/A';
                $detailsUser = User::where('id', $claims->created_by)->first(array('firstName', 'lastName'));
                if ($detailsUser) {
                    $claim_handler = $detailsUser->firstName . ' ' . $detailsUser->lastName;
                }
                return $claim_handler;
            })
            ->editColumn('created_at', function ($claims) {
                return $claims->created_at->diffForHumans();
            })
            ->addColumn('actions', function ($claims) {
                $actions = '<a href="' . route('admin.claims.show', $claims->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="flaticon-eye"></i>
                            </a>';
                return $actions;
            })
            ->editColumn('status', function ($claims) {
                if ($claims->status == 'Approved') {
                    $return = '<span class="kt-font-bold kt-font-accent">Approved</span>';
                } elseif ($claims->status == 'Pending') {
                    $return = '<span class="kt-font-bold kt-font-primary">Pending</span>';
                } else {
                    $return = '<span class="kt-font-bold kt-font-danger">Rejected</span>';
                }

                return $return;
            })
            ->editColumn('claim_type', function ($claims) {
                if ($claims->claim_type == "Accident") {
                    $return = '<span class="kt-font-bold kt-font-accent">Motor Accident</span>';
                    return $return;
                } else {
                    $return = '<span class="kt-font-bold kt-font-accent">' . $claims->claim_type . '</span>';
                    return $return;
                }
            })
            ->editColumn('claim_sub_status', function ($claims) {
                $claimsSubStatus = ClaimAccident::where('claim_id', $claims->id)->first();
                if ($claimsSubStatus != null) {
                    if ($claims->claim_type == "Accident" && $claimsSubStatus->claim_sub_status != null) {
                        $return = '<span class="kt-font-bold kt-font-primary">' . $claimsSubStatus->claim_sub_status . '</span>';
                        return $return;
                    } else {
                        $return = '-';
                        return $return;
                    }
                } else {
                    $return = '-';
                    return $return;
                }
            })
            ->rawColumns(['actions', 'status', 'claim_sub_status', 'claim_type','claim_handler'])
            ->make(true);
    }

    /*
     * method to check existing user
     * param: omang id / passport
     * return JSON
     */
    public function checkUserOmang(Request $request)
    {
        if ($request->get('omang') != null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orwhere('passport', $request->get('passport'))->first();
        } elseif ($request->get('omang') != null && $request->get('passport') == null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->first();
        } elseif ($request->get('omang') == null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('passport', $request->get('passport'))->first();
        }

        if ($profile != null) {
            $customerData = Customer::where('id', $profile->customer_id)->first(array('firstName', 'lastName', 'email', 'cellphone'));
            return response()->json(['count' => $profile, 'customerData' => $customerData]);
        } else {
            return response()->json(['count' => $profile]);
        }
    }

    /*
     * method to check activation
     * param: request: code
     * return json
     */
    public function checkActivation(Request $request)
    {
        $code = Activation::where('activation_code', $request->get('code'))->first(array('product_id', 'product_plan_id', 'status'));
        if ($code != null) {
            return response()->json(['count' => $code]);
        } else {
            return response()->json(['count' => $code]);
        }
    }

    /*
     * method redirects to thankYou page
     */
    public function getThankYouPage()
    {
        $statusMessage = 'YES';
        return view('alphaFe.thankyou2', compact('statusMessage'));
    }

    /*
     * check whether the user already claim policy using same vehicle claim number
     */
    public function checkUserVehicle(Request $request)
    {
        if ($request->get('omang') != null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') == null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') != null && $request->get('passport') == null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
        }

        if ($profile == null) {
            return response()->json(['count' => '0']);
        } else {
            $vehicle = Vehicle::where('customer_id', $profile->customer_id)->where('vehiclePlate', $request->get('vehiclePlate'))->count();
            return response()->json(['count' => $vehicle]);
        }
    }


    /*
     * generates activation code
     */
    public function generateActivationCode(Request $request)
    {
        $latest_code = Activation::orderBy('id', 'DESC')->first(array('serial_code', 'activation_code', 'group_id'));
        if ($latest_code == null) {
            $serial_code = 'AAAAAA';
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $group_id = 1;
        } else {
            $serial_code = $latest_code->serial_code;
            $group_id = $latest_code->group_id + 1;
            $activation_code = Helper::gen_ustring(10000000, 99999999);
        }
        $activation = new Activation();
        if ($latest_code != null) {
            $var = base_convert($serial_code, 36, 10);
            $var++;
            //To check if number in serial code than increament
            while (preg_match('~[0-9]+~', strtoupper(base_convert($var, 10, 36)))) {
                $var++;
            }
            $serial_code = strtoupper(base_convert($var, 10, 36));
        }
        $activation->group_id = $group_id;
        $activation->serial_code = $serial_code;
        $check = Activation::where('activation_code', $activation_code)->count();
        while ($check > 0) {
            $activation_code = Helper::gen_ustring(10000000, 99999999);
            $check = Activation::where('activation_code', $activation_code)->count();
        }
        $activation->activation_code = $activation_code;
        $activation->status = 0;
        $saved = $activation->save();
        return response()->json(['status' => 'success', 'code' => $activation->activation_code]);
    }

    /*
     * updarte banking details
     * param: request policy id
     */
    public function updateBanking(Request $request)
    {
        $policy = Policy::where('id', $request->policy_id)->with('product')->first();
        $product = Product::where('id', $policy->product_id)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
        $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));
        $regionVat    = Region::where('id', $product->region_id)->first();
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));
            $premium      = ($product_plan->premium * ($regionVat['vat'] / 100)) + $product_plan->premium;
        } else {
            $premium = $request->get('premium');
        }

        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        $update = $realpay->updateClientRealpay($request->all());

        if($update == true){
            CustomerBanking::where('policy_id', $request->policy_id)->update([
                'bankName'      => $request->bankName,
                'branchCode'    => $request->branchCode,
                'accountNumber' => $request->accountNumber,
                'accountType'   => $request->bankAccountType,
            ]);

            return Redirect::route('admin.policy.edit',$policy->id)->with('success', 'Customer banking information updated Successfully');

        }else{
            return Redirect::route('admin.policy.edit',$policy->id)->with('error', 'Customer banking information updated failed');
        }



//        switch ($request->billingOption) {
//            case 'VCS':
//                $vcs = new VcsController;
//                try {
//                    return $vcs->graphiteVcsPayment($policy->policyNumber, $premium, $policy->id, $policy->trial_period);
//                } catch (\Exception $e) {
//                    return Redirect::back()->with('error', $e->getMessage());
//                }
//                break;
//            case 'Orange':
//                $orangeMoney = new OrangeMoneyController();
//                try {
//                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
//                } catch (\Exception $e) {
//                    return Redirect::back()->with('error', $e->getMessage());
//                }
//                break;
//            case 'RealPay':
//
//                $realPay = new RealPayController();
//                try {
//                    return $realPay->addClientRealPay($policy, $premium);
//                } catch (\Exception $e) {
//                    return Redirect::back()->with('error', $e->getMessage());
//                }
//                break;
//            default:
//                return Redirect::back()->with('error', 'Please select a payment vendor')
//                    ->withInput($request->all());
//        }

    }

    /*
     * deletes specified motor items
     */
    public function specifiedItemDelete(Request $request)
    {
        $factorValues = PolicyMotorItems::find($request->id)->delete();
        return response()->json(['count' => $factorValues]);
    }

    /*
     * provides payment form on live environment
     * param: policy id ($id)
     * return view: policy payment page
     */
    public function graphitePaymentForm($id)
    {
        try {
            $str = base64_decode($id);
            $paymentUrl = PaymentUrls::where('id', $str)->first();
            $policy = Policy::where('id', $paymentUrl->policy_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->first();
            $product = Product::where('id', $policy->product_id)->first();
            $vendors = PaymentVendor::where('status', '1')->get();
            return view('admin.policy.payment', compact('paymentUrl', 'policy', 'customer', 'product', 'vendors', 'amount'));
        } catch (Exception $ex) {
            return $ex;
        }
    }

    public function getPolicyBalance($id)
    {
        try {
            $ledger = Ledger::where('policy_id', $id)->orderBy('id', 'desc')->first(array('balance'));
            if($ledger == NULL)
                return response()->json(['error' => '1', 'message' => 'Invalid Policy Id'], 401);
            else
                return response()->json(['error' => '0', 'balance' => $ledger->balance], 200);
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }

    /*
     * methods process payment from live environment
     * param: policy id ($id)
     * return json
     */
    public function processGraphitePayment($id, Request $request)
    {
        try {
            $str = base64_decode($id);
            $paymentUrl = PaymentUrls::where('id', $str)->first();
            $paymentUrl->status = '1'; //change status to 1, used
            $paymentUrl->save(); //save the change
            $policy = Policy::where('id', $paymentUrl->policy_id)->first();
            $product = Product::where('id', $policy->product_id)->first();
            $customer = Customer::where('id', $policy->customer_id)->first();
            switch ($request->billingOption) {
                case 'VCS':
                    $vcs = new PaymentController;

                    if ($policy->product_id == 3 && $policy->leadSource == 'WhatsappApi') {
                        return $vcs->handlePaymentForQuoteVariable($policy->policyNumber, $policy->leadSource);
                    } else if ($policy->product_id != 3 && $policy->leadSource == 'WhatsappApi') {
                        return $vcs->handlePaymentForQuoteInstant($policy->policyNumber, 'WhatsappApi');
                        break;
                    }

                    return $vcs->handlePayment($policy->policyNumber, 'graphite');
                    break;
                case 'Orange':
                    $orangeMoney = new OrangeMoneyController();
                    return $orangeMoney->webPayIntiliazer($policy->policyNumber, $paymentUrl->amount);
                    break;
                case 'RealPay':
                    $realPay = new RealPayController();
                    return $realPay->addClientRealPay($policy, $paymentUrl->amount);
                    break;
                default:
                    return Redirect::back()->with('error', 'Please select a payment vendor')
                        ->withInput($request->all());
            }
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), $exception->getLine());
        }
    }

    /*
     * shows payments URLs related with policy
     * param: policy id
     * return json
     */
    public function paymentURLdata($id)
    {
        try {
            $paymentUrls = new PaymentUrls();
            $response = $paymentUrls->getPaymentURLDataUsingPolicyId($id);
            $data = $response->getData();
            return response()->json($data);
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), $exception->getLine());
        }
    }

    /*
     * modal to confirm updation of data
     * return json
     */
    public function getModalEdit(Request $request)
    {
        $body = "Confirm the following details before sending";
        return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
    }

    /*
     *
     */
    public function tempStore(Request $request)
    {
        if ($request->get('omang') != null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') == null && $request->get('passport') != null) {
            $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first(array('customer_id'));
        } elseif ($request->get('omang') != null && $request->get('passport') == null) {
            $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first(array('customer_id'));
        }

        if ($profile == null) {
            $user = new Customer();
            $user->firstName = $request->get('fname');
            $user->lastName = $request->get('lname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('cellphone');
            $user->password = Hash::make('111111');
            $user->save();
            $profile = new CustomerProfile();
            $profile->customer_id = $user->id;
            $profile->gender = $request->get('gender');
            $profile->address = $request->get('address');
            $profile->omang = $request->get('omang');
            $profile->passport = $request->get('passport');
            $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profile->save();
            $kyc = new KYC();
            $kyc->customer_id = $user->id;
            if ($request->hasFile('driving_license')) {
                $file = $request->file('driving_license');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/driving_license' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->driving_license = $filePath;
            }
            if ($request->hasFile('omang')) {
                $file = $request->file('omang');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/omang' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->omang = $filePath;
            }
            if ($request->hasFile('proof_residence')) {
                $file = $request->file('proof_residence');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_residence' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->proof_residence = $filePath;
            }
            if ($request->hasFile('proof_income')) {
                $file = $request->file('proof_income');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/proof_income' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->proof_income = $filePath;
            }
            if ($request->hasFile('passport')) {
                $file = $request->file('passport');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $kyc->customer_id . '/' . 'Customer' . '/passport' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $kyc->passport = $filePath;
            }
            $kyc->compliance = 0;

            $kyc->save();
        } else {
            $user = Customer::where('id', $profile->customer_id)->first();
            $user->firstName = $request->get('fname');
            $user->lastName = $request->get('lname');
            $user->email = $request->get('email');
            $user->cellphone = $request->get('cellphone');
            $user->save();

            $profile = CustomerProfile::where('customer_id', $profile->customer_id)->first();
            $profile->customer_id = $user->id;
            $profile->gender = $request->get('gender');
            $profile->address = $request->get('address');
            $profile->omang = $request->get('omang');
            $profile->passport = $request->get('passport');
            $profile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $profile->save();
        }
        //Latestid for Policy Number
        $latest = Policy::latest('policyNumber')->orderBy('id', 'DESC')->first(array('policyNumber'));
        if ($latest == null) {
            $latest = collect();
            $latest->id = 0;
        }
        $product = Product::where('id', $request->get('product'))->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code'));
        $policy = new Policy();
        $policy->customer_id = $user->id;
        $policy->agent_id = Auth::id();
        $policy->leadSource = 'G-ACT';
        $policy->note = $request->get('note');
        $policy->product_id = $request->get('product');
        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $request->plan)->first(array('sum_assured', 'premium', 'slug'));
            $premium = round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2);
            $policy->plan_id = $request->get('plan');
            $policy->sum_assured = $product_plan->sum_assured;
            $policy->premium = round($product_plan->premium, 2);
            $policy->vat = round(($product_plan->premium * ($regionVat / 100)), 2);
            $policy->vat_percent = $regionVat;
        } else {
            $premium = ($request->get('premium') * ($regionVat / 100)) + $request->get('premium');
            $policy->premium = $request->get('premium');
            $policy->vat = $request->get('premium') * ($regionVat / 100);
            $policy->vat_percent = $regionVat;
            $policy->sum_assured = $request->get('sum_assured');
        }
        $policy->policyNumber = 'MIS' . Carbon::now()->year . str_pad((substr($latest->policyNumber, -6) + 1), 6, '0', STR_PAD_LEFT);
        $policy->status = 0;
        $policy->has_vehicle = $product->has_vehicle;
        $policy->has_member = $product->has_member;
        $policy->preinspection = $product->preinspection;
        $policy->is_motor_items = $product->is_motor_items;
        $policy->limit = $product->limit;
        $policy->kyc_customer = $product->kyc_customer;
        $policy->kyc_recipient = $product->kyc_recipient;

        if ($product->has_activation_code) {
            $activation = Activation::where('activation_code', $request->get('activation_code'))->first();
            $activation->product_id = $request->get('product');
            $activation->product_plan_id = $request->get('plan');
            $activation->status = 0; //return to 1 after test
            $activation->save();

            $policy->activation_code = $request->get('activation_code');
            $policy->trial_period = $activation->trial_periods;
            $policy->trial_coverage = $activation->trial_coverage;
            $policy->serial_code = $activation->serial_code;
        }
        $saved = $policy->save();

        $mains = FactorMain::where('product_id', $request->get('product'))->where('status', 1)->get(array('id', 'name', 'type'));
        foreach ($mains as $main) {
            if (is_array($request->get('factor_' . $main->id))) {
                foreach ($request->get('factor_' . $main->id) as $key => $value_id) {
                    $factor = new PolicyFactor();
                    $factor->policy_id = $policy->id;
                    $factor->factor_main_id = $main->id;
                    $factor->name = $main->name;
                    $factor->type = $main->type;
                    $value = FactorSubType::where('id', $value_id)->first(array('name', 'factor'));
                    $factor->factor_value_id = $value_id;
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $saved = $factor->save();
                }
            } else {
                $factor = new PolicyFactor();
                $factor->policy_id = $policy->id;
                $factor->factor_main_id = $main->id;
                $factor->name = $main->name;
                $factor->type = $main->type;
                $value = FactorSubType::where('id', $request->get('factor_' . $main->id))->first(array('name', 'factor'));
                if ($main->type == 'Input Field') {
                    $factor->value_name = $request->get('factor_' . $main->id);
                } else {
                    $factor->value_name = $value->name;
                    $factor->factor = $value->factor;
                    $factor->factor_value_id = $request->get('factor_' . $main->id);
                }
                $saved = $factor->save();
            }
        }
        $banking = new CustomerBanking();
        $banking->customer_id = $user->id;
        $banking->policy_id = $policy->id;
        $banking->accountNumber = $request->get('accountNumber');
        $banking->billing = $request->get('billingMethod');
        $banking->billingCell = $request->get('billingCell');
        $banking->bankName = $request->get('bankName');
        $banking->branchCode = $request->get('branchCode');
        $banking->accountType = $request->get('bankAccountType');
        $saved = $banking->save();

        $vehicle = new Vehicle();
        $vehicle->customer_id = $user->id;
        $vehicle->policy_id = $policy->id;
        $vehicle->vehiclePlate = $request->vehiclePlate;
        $vehicle->chassisNo = $request->chassisNo;
        $vehicle->odometer = $request->odometer;
        $vehicle->condition = $request->condition;
        $vehicle->purpose = $request->purpose;
        $vehicle->make = $request->make;
        $vehicle->model = $request->model;
        $vehicle->year = $request->date;
        $vehicle->condition = $request->condition;
        $vehicle->cylinders = $request->cylinders;
        $vehicle->cubic_capacity = $request->cubic_capacity;
        $vehicle->seats = $request->seats;
        $vehicle->engineNo = $request->engineNo;
        $vehicle->is_private = $request->is_private;
        $vehicle->is_modified = $request->is_modified;
        $vehicle->is_tracking = $request->is_tracking;
        $vehicle->is_imported = $request->is_imported;

        if ($request->hasFile('front')) {
            $file = $request->file('front');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->front = $filePath;
        }
        if ($request->hasFile('back')) {
            $file = $request->file('back');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->back = $filePath;
        }
        if ($request->hasFile('right')) {
            $file = $request->file('right');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->right = $filePath;
        }
        if ($request->hasFile('left')) {
            $file = $request->file('left');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->left = $filePath;
        }
        if ($request->hasFile('vehicleRegistration')) {
            $file = $request->file('vehicleRegistration');
            $name = $file->getClientOriginalName();
            $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $vehicle->vehicleRegistration = $filePath;
        }
        $saved = $vehicle->save();
        if ($request->main != null) {
            for ($i = 0; $i < count($request->main); $i++) {
                $policyCover = new PolicyCoverage();
                $policyCover->policy_id = $policy->id;
                $policyCover->main = $request->main[$i];
                $policyCover->coverage_value = $request->cover_value[$i];
                $policyCover->discount = $request->type[$i];
                $policyCover->type = $request->disccount_type[$i];
                $policyCover->value = $request->type_value[$i];
                $saved = $policyCover->save();
            }
        }
        $sms = new SmsMessaging();
        $sms->sendTsosologoSMS(1, $user->cellphone, $policy->policyNumber,($policy->premium+$policy->vat),$product_plan->slug, $user->firstName,null);
        $status = 'Successful';

        $urlValue = \Config::get('values.graphite_url'); // get the URL
        $paymentUrl = new PaymentUrls();
        $paymentUrl->cellphone = $user->cellphone;
        $paymentUrl->policy_id = $policy->id;
        $paymentUrl->amount = $policy->premium;
        $paymentUrl->request_from = 'G-ACT';
        $paymentUrl->status = 0;
        if ($paymentUrl->save()) {
            $addPyamentUrl = PaymentUrls::findorFail($paymentUrl->id);
            $addPyamentUrl->url = $urlValue . 'loadPaymentForm/' . base64_encode($paymentUrl->id);
            $addPyamentUrl->save();
            return redirect()->to($addPyamentUrl->url);
        }
        switch ($request->billingOption) {
            case 'VCS':
                $vcs = new VcsController;
                return $vcs->graphiteVcsPayment($policy->policyNumber, $premium, $policy->id, $activation->trial_periods);
                break;
            case 'Orange':
                $orangeMoney = new OrangeMoneyController();
                return $orangeMoney->webPayIntiliazer($policy->policyNumber, $premium);
                break;
            case 'RealPay':
                $realPay = new RealPayController();
                return $realPay->addClientRealPay($policy, $premium);
                break;
            default:
                return Redirect::back()->with('error', 'Please select a payment vendor')
                    ->withInput($request->all());
        }

        return Redirect::route('admin.policy.index')->with('success', 'Policy Created Successfully');
    }

    /*
     * method to change policy payment status
     * parameter: (request policy id)
     * return json
     */
    public function changePaymentStatus(Request $request)
    {
        if (auth()->user()->hasRole('Super Admin')) {
            try {
                $payment = new PaymentController();
                $referenceNumber = $payment->generate_string();
                $policy = Policy::where('id',$request->policy_id)->first();
                $policy->status = 1;
                $saved = $policy->save();
                $transaction = new Transaction();
                $transaction->customer_id = $policy->customer_id;
                $transaction->policyNumber = $policy->policyNumber;
                $transaction->referenceNumber = $referenceNumber;
                $transaction->status = 'SUCCESS';
                $transaction->paymentDescription = 'Test Mode';
                $transaction->amount = 1.00;
                $transaction->save();

                $paymentData['policyNumber'] = $policy->policyNumber;
                $paymentData['referenceNumber'] = $referenceNumber;
                $paymentData['amount'] = 1.00;
                $paymentData['status'] = 'SUCCESS';
                $paymentData['paymentDate'] = \Carbon\Carbon::now()->format('Y-m-d');
                $paymentData['paymentMethod'] = 'Test Trigger Button';
                $paymentData['numberOfInstalmentsPaid'] = 1;
                $paymentData['note'] = 'Testing';

                $policyController = new PolicyController();
                $saveData = $policyController->updatePaymentTransactions($paymentData);

                if ($transaction->save() && $policy->save()) {
                    Helper::ledgerStore($policy->customer_id, 'POLICY', $policy->id, $policy->product_id, 'NEWBUSINESS');
                }
                return response()->json(['status' => 'success']);
            } catch (\Exception $ex) {
                //throw $th;
                dd($ex->getMessage().' '.$ex->getLine().' '.$ex->getFile().' '.$ex->getCode());
                return response()->json(['error' => $ex->getMessage()]);
            }
        } else {
            return redirect()->back();
        }
    }

    /*
     * method to retrieve all the models related to make
     * param: vehicle model
     * returns JSON
     */
    public function checkMakeModel(Request $request)
    {
        $variant = vehicleMake::where('s_Make', $request->get('make'))->pluck('s_Variant');
        return response()->json(['count' => $variant]);
    }

    /*
     * policy view page for specific policy
     * param: policy id
     * returns policy view page
     */

    public function policyView($id, Request $request)
    {
        $fileNames = Lookup::where('key', 'file_type')->get(array('key', 'value'));
        $policy     = Policy::where('id', $id)->first();
        $banks      = Banks::all();
        $PolicyLedgers = PolicyLedgers::orderBy('id', 'DESC')->where('policy_id', $policy->id)->first();

        $policyactivatecancelleddates = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->first('cancelled_date');

        $cancelNote = PolicyCoverCancelNote::where('policy_id', $policy->id)
            ->where('doc_type', 'Cancel')
            ->orderBy('id', 'DESC')
            ->first(array('path'));

        $policy_term = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','desc')->get();
        $scheduleTransactionCount = ScheduleTransaction::where('policy_number',$policy->policyNumber)->count();
        $coverNote = PolicyCoverCancelNote::where('policy_id', $policy->id)
            ->where('doc_type', 'Cover')
            ->orderBy('id', 'DESC')
            ->first(array('path'));

        $feedback                  = CustomerFeedback::where('policy_id',$policy->id)->orderBy('id','desc')->first();
        $claims                    = Claim::where('policy_id', $policy->id)->get();
        $vehicleOld                = vehicleOld::where('policy_id', $policy->id)->orderBy('id','desc')->get();

        $productPlan               = Productplan::where('id', $policy->plan_id)->first(array('name', 'sum_assured'));
        $user                      = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
        $user->profile->dob        = Carbon::parse(str_replace("/", "-", $user->profile->dob))->format('d-m-Y');
        $user->profile->state_name = State::where('id', $user->profile->state)->first('name');
        if(isset($user->profile->sourceOfIncome) && $this->isJSON($user->profile->sourceOfIncome)){
            $user->profile->sourceOfIncome = json_decode($user->profile->sourceOfIncome, true);
        }
        // $user->profile->city_name  = City::where('id', $user->profile->city)->first('name');
        $kyc                  = KYC::where('customer_id', $policy->customer_id)->first();

        if($kyc && $kyc->passportIssuingCountry)
            $passpostIssueCountry = Country::where('id', $kyc->passportIssuingCountry)->first(array('name'));
        else
            $passpostIssueCountry = null;
        $years = range(1990,Carbon::now()->year);
        $customerVerification = '';
        $customerMati         = '';
        $matiDetails          = array();
        $matiVerifData        = '';

        if ($user->mati_identity != null) {
            $customerMati = CustomerMati::where('identity_id', $user->mati_identity)->orderBy('id', 'DESC')->first();
            if ($customerMati && $customerMati != null) {
                $matiVerifData = json_decode($customerMati->response);
                $customerVerification = CustomerMati::where('verification_id',$customerMati->verification_id)->get();
            }
        }

        $product = Product::where('id', $policy->product_id)->first(array('id', 'name', 'is_motor_items', 'premium_type_id', 'region_id', 'type', 'has_vehicle'));

        $productFactors = FactorMain::with('value')->where('product_id', $policy->product_id)->get(array('id', 'name', 'type'));
        $policyFactors = PolicyFactor::where('policy_id', $policy->id)->get();

        foreach ($productFactors as $productFactor) {
            $policyFactors = PolicyFactor::where('policy_id', $policy->id)->where('factor_main_id', $productFactor->id)->get(array('factor_value_id', 'value_name', 'name'));
            if ($productFactor->type == 'Input Field') {
                $productFactor->policyFactors = $policyFactors->pluck('value_name')->toArray();
            } else {
                $productFactor->policyFactors = $policyFactors->pluck('factor_value_id')->toArray();
            }

            $productFactor->policyFactorsValueName = $policyFactors->pluck('value_name')->toArray();
        }
        $members = PolicyMember::where('policy_id', $policy->id)->get();
        $beneficiaries = PolicyBeneficiary::where('policy_id', $policy->id)->get();
        $policyCover = PolicyCoverage::where('policy_id', $policy->id)->get(array('id', 'main', 'coverage_value', 'discount', 'type', 'value'));
        $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
        if($banking != null && $banking->billing == 'RealPay')
        {
            $banking->bank_name   = Banks::where('bank_number', $banking->bankName)->value('bank_name');
            $banking->bank_branch = BankBranches::where('branch_id', $banking->branchCode)->value('name');
        }
        // dd($banking);
        $dataModel = null;
        $dataMake = null;
        $reratedPremiumQuotes = null;
        $premiumCalcDetails = null;
        $vehicleMakes = NULL;
        $vehicleModels = NULL;
        $vehicle_purpose = NULL;
        $vehicle = NULL;
        $agent_name = NULL;
        $PolicyCoverages = '';

        if($product->id == 6){
            $vehiclePolicyTyreRim = PolicyTyreRim::where('policy_id', $policy->id)->get();
            $vehicleDetailsTyreRim = Vehicle::where('policy_id', $policy->id)->get();
            $vehiclePurposeTyreRim = Lookup::where('key', 'vehicle_purpose')->whereIn('id', [29,139,140])->get(array('id', 'value'));
        }else{
            $vehiclePolicyTyreRim = NULL;
            $vehicleDetailsTyreRim = NULL;
            $vehiclePurposeTyreRim = NULL;
        }
        $PolicyBundled = PolicyBundled::where('policy_id',$policy->id)->get();
        if($policy->is_bundled == 1)
         $p_idddd = 0;
        foreach($PolicyBundled as $PolicyBundleds){
            if($PolicyBundleds->product_id == 3)
            $p_idddd = 3;
        }

        if($product->id != 6){
            if($policy->has_vehicle != 0 || ($policy->is_bundled == 1 && $p_idddd == 3) ){
                $vehicle = Vehicle::where('policy_id', $policy->id)->get();
                foreach($vehicle as $vehicles)
                if ($vehicle) {
                    $Terms_id = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','desc')->first('id');
                    if($Terms_id != NULL)
                    $PolicyCoverages = PolicyCoverages::where('vehicle_id', $vehicles->id)->where('policy_id', $policy->id)->where('term_id', $Terms_id->id)->orderBy('id','desc')->get();
                    else
                    $PolicyCoverages = PolicyCoverages::where('vehicle_id', $vehicles->id)->where('policy_id', $policy->id)->orderBy('id','desc')->get();

                    $vehicleMakes = DB::table('tb_prmotormakemodels')
                        ->selectRaw('DISTINCT s_Make')
                        ->get(array('s_Make'));
                    if ($vehicles && $vehicles->make != null) {
                        $vehicleModels = DB::table('tb_prmotormakemodels')->where('s_Make', $vehicles->make)
                            ->selectRaw('DISTINCT s_Variant')
                            ->get(array('s_Variant'));
                    } else {
                        $vehicleModels = DB::table('tb_prmotormakemodels')
                            ->selectRaw('DISTINCT s_Variant')
                            ->get(array('s_Variant'));
                    }
                    $vehicle_purpose = Lookup::where('key', 'vehicle_purpose')->get(array('id', 'value'));
                }
            }

        }

        $agents = User::role('Agent')->where('active', 1)->get(array('id', 'firstName', 'lastName'));
            if ($policy->agent_id != null) {

                 $agent_name = User::leftJoin('agencies', 'agencies.id', 'users.agency_id')
                ->where('users.id', $policy->agent_id)
                ->first(array('users.firstName', 'users.lastName', 'agencies.id as agency_id', 'agencies.status as agency_status', 'agencies.name as agency_name'));
                if($agent_name && $agent_name->agency_id != null){
                    $agency_id = $agent_name->agency_id;
                }else{
                    $agency_id = null;
                }
                // $agency_id = $agent_name->agency_id != null ?$agent_name->agency_id : null ;

            }else{
                $agency_id = null;
            }

        $emailDocs = Documents::where('product_id', $policy->product_id)
            ->orWhere('product_id', -1)
            ->where('status', 1)
            ->get(array('name', 'link'));

        $EmailDocsPolicyBundled = PolicyBundled::where('policy_id',$policy->id)->get(array('policyDocument'));

        $policyMotorItems = PolicyMotorItems::where('policy_id', $policy->id)->get(array('id', 'item_name', 'item_value', 'policy_id'));
        $count = count($policyMotorItems);
        $motor_items = DB::table('tb_prappssupppersprops')->where('n_Coverage_FK', 148)->select('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK')->get(array('s_PersPropScreenName', 'n_PRAppsSuppPersprop_PK'));

        $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('paymentAlreadyLog',1)->orderBy('id', 'DESC')->first(array('status', 'referenceNumber'));
        if (!isset($transaction)) {
            $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status', 'referenceNumber'));
        }
        if ($transaction == null)
            $transaction = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('status', 'referenceNumber'));

        $regionVat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
        if ($product->premium_type_id == 11) {
            $product_plan = Productplan::where('id', $policy->plan_id)->first(array('sum_assured', 'premium'));
            $premium = $policy->premium/*round(($product_plan->premium * ($regionVat / 100)) + $product_plan->premium, 2)*/;
        } else {
            $premium = $policy->premium/*($request->premium * ($regionVat / 100)) + $request->premium*/;
            $policy->premium = $premium;
            $policy->vat = $request->get('premium') * ($regionVat / 100);
            //$policy->sum_assured = $request->sum_assured;
        }

        $policies_reinstate = PolicyReinstate::where('policy_id',$policy->id)->orderBy('id','desc')->first();
        $balance_fresh = null;
        $balance_arrears = null;
        $balanceArrears = null;
        if(isset($policy) && $policy->status == 2){
            $balance_arrears = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(array('balance'));
            if (isset($balance_arrears)) {
                $balanceArrears = number_format(abs($balance_arrears->balance), 2, '.', '');
            } else {
                $balanceArrears = 0;
            }

            // $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
            $balance_fresh = null;
            // if (isset($data)) {
            //     $balance_fresh = $data->new_value;
            // } else {
                $balance_fresh = $policy->premium;
            // }

        }

        if ($policy->storeID != null) {
            $store = Stores::where('id', $policy->storeID)->first();
            if ($store && $store->name) {
                $storeName = $store->name;
            } else {
                $storeName = null;
            }
        } else {
            $storeName = null;
        }
        $policy_cellphone = PolicyCellPhone::leftjoin('policy_cellphone_device_status', 'policy_cellphone_device_status.policy_cellphone_id', 'policy_cellphone.id')
            ->where('policy_cellphone.policy_id', $policy->id)->get(array('policy_cellphone.*', 'policy_cellphone_device_status.status'));
        $updateDevices = 0;
        foreach ($policy_cellphone as $device) {
            $device->brands = DeviceMakeModel::where('device_type', $device->device_type)->where('make_id', NULL)->get(array('id', 'name'))->toArray();
            $make = DeviceMakeModel::where('device_type', $device->device_type)->where('name', $device->cell_phone_make)->first(array('id'));
            $device->models = DeviceMakeModel::where('make_id', $make->id)->get(array('id', 'name'))->toArray();
            if ($device->cell_phone_front != NULL && $device->cell_phone_back != NULL && $device->cell_phone_left != NULL && $device->cell_phone_right != NULL && $device->cell_phone_top != NULL && $device->cell_phone_bottom != NULL)
                $device->images = 1;
            else {
                $device->images = 0;
                $updateDevices = 1;
            }
        }
        $quote_id = '';
        if ($product->id == 3) {
            if ($policy->quoteNumber != null){
                $quote_id = Quote::where('quoteCode', $policy->quoteNumber)->first(array('id','quoteCode'));
            }else{
                $quote_id = null;
            }
        }

        if ($product->id == 3 || $product->id == 2) {
            if ($policy->quoteNumber != null) {
                $premiumCalcDetails = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();
                $reratedPremiumQuotes = ReratedPremiumQuote::where('rate_id', $premiumCalcDetails->ratings_id)->orderBy('id', 'DESC')->first();
                $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($premiumCalcDetails->is_imported);
               //dd($premiumCalcDetails->is_imported,$dataMake);
                $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($premiumCalcDetails->make, $premiumCalcDetails->is_imported, $premiumCalcDetails->year);
                //dd($dataModel,$premiumCalcDetails->make, $premiumCalcDetails->is_imported, $premiumCalcDetails->year);
            } else {
                $premiumCalcDetails = null;
                $reratedPremiumQuotes = null;
                $premiumCalcDetails = new \stdClass();
                if (isset($vehicle->first()->is_imported)) {
                    $premiumCalcDetails->is_imported  = $vehicle->first()->is_imported == 0 ? "No" : "Yes";
                } else {
                    $premiumCalcDetails->is_imported  = null;
                }

                if (isset($vehicle->first()->make)) {
                    $premiumCalcDetails->make = $vehicle->first()->make;
                } else {
                    $premiumCalcDetails->make  = null;
                }

                if (isset($vehicle->first()->model)) {
                    $premiumCalcDetails->model = $vehicle->first()->model;
                } else {
                    $premiumCalcDetails->model  = null;
                }

                if (isset($vehicle->first()->year)) {
                    $premiumCalcDetails->year = $vehicle->first()->year;
                } else {
                    $premiumCalcDetails->year  = null;
                }

                $premiumCalcDetails->estimatedValue = $policy->sum_assured;

                if (isset($vehicle->first()->claim_count)) {
                    $premiumCalcDetails->priorAccidents = $vehicle->first()->claim_count;
                } else {
                    $premiumCalcDetails->priorAccidents  = null;
                }

                $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($premiumCalcDetails->is_imported);
                $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($premiumCalcDetails->make, $premiumCalcDetails->is_imported, $premiumCalcDetails->year);
                $premiumCalcDetails->quoteNumber  = null;
                $premiumCalcDetails->ratings_id = null;
                $premiumCalcDetails->premiumMonthly = $policy->premium;
                $premiumCalcDetails->premium3Inst = $policy->premium;
                $premiumCalcDetails->premiumAnnually = $policy->premium;
                $premiumCalcDetails->discount_surcharge = null;
                $premiumCalcDetails->premium_rate = null;
                $premiumCalcDetails->ratio = null;
            }
             $premiumCalcDetails->priorAccidents = Claim::where('policy_id',$policy->id)->count();
            $countLog = PolicyPremiumReratingLog::where('policy_number',$policy->policyNumber)->count();
            $setting = QuoteSettings::orderBy('id','DESC')->first(array('policy_premium_edit_limit'));
            $premiumCalcDetails->can_edit = ($countLog < $setting->policy_premium_edit_limit) ? 1 : 0;
        }
        // if($product->id == 3){
        //     // if ($vehicle->is_imported == 1) {
        //     //     $status = 'Yes';
        //     // } else {
        //     //     $status = 'No';
        //     // }
        //     $dataMake = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleMakes($premiumCalcDetails->is_imported);
        //     $dataModel = app('AlphaDirect\Http\Controllers\QuoteController')->getVehicleModels($premiumCalcDetails->make,$premiumCalcDetails->is_imported,$premiumCalcDetails->year);
        // }
        //get users agency

        $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
        if ($balance != null) {
            $balance = $balance->balance;
        } else {
            $balance = 0;
        }
        $motorPreminum = NULL;
        $newConvertedAmt = NULL;

        if ($policy->product_id == 3 && $policy->premium) {
            $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
            // dd($motorPreminum);

            $txLog = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                ->where('status','!=','CANCELLED')
                ->where('policyNumber','!=','FAILED')
                ->orderBy('id', 'DESC')->get();
            if ($txLog == null){
                $txLog = Transaction::where('policyNumber', $policy->policyNumber)
                ->where('status','!=','CANCELLED')
                ->where('policyNumber','!=','FAILED')
                ->orderBy('id', 'DESC')->get();
            }

            if ($policy->status != 1 && !isset($txLog) && $policy->premium_freq == 3) {
                # code... 3 inst and monthly
                if ($policy->product_id == 3 && isset($policy->premium)) {
                    // $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                    if (isset($motorPreminum)) {
                        $newConvertedAmt = $this->getMotorCompPremiumForConvertingFreq($motorPreminum['annual'],null);
                    }
                }
            } elseif ($policy->status == 1 && isset($txLog) && $policy->premium_freq == 2) {
                # code... annual and monthly provided only 1 or two installment has been made

                if ($policy->product_id == 3 && isset($policy->premium)) {
                    // $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                    if (isset($motorPreminum)) {
                        $countTx = $txLog->count();
                        if ($countTx < 3) {
                            $totalAmtPaid = $txLog->sum('amount');
                            $balanceAmt = $motorPreminum['annual'] - $totalAmtPaid;
                            $newConvertedAmt = $this->getMotorCompPremiumForConvertingFreq($balanceAmt,null);
                        }
                    }
                }
            } elseif ($policy->premium_freq == 1) {
                if ($policy->product_id == 3 && isset($policy->premium)) {
                    // $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                    if (isset($motorPreminum)) {
                        $totalAmtPaid = 0;
                        if (isset($txLog)) {
                            $totalAmtPaid = $txLog->sum('amount');
                        }

                        $balanceAmt = $motorPreminum['annual'] - $totalAmtPaid;
                        $newConvertedAmt = $this->getMotorCompPremiumForConvertingFreq($balanceAmt,null);
                    }
                }
            } elseif ($policy->status == 0) {
                if (isset($motorPreminum)) {
                    $newConvertedAmt = $motorPreminum;
                }
            }

        }
        // dd($newConvertedAmt);
        $omang_passport_id = '-';
        if($user->profile->omang && $user->profile->passport == null){
            $omang_passport_id = 'Omang: '.$user->profile->omang.' - '.'Passport: N/A';
        }else{
            $omang_passport_id = $omang_passport_id = 'Omang: N/A'.' - '.'Passport: '.$user->profile->passport;
        }


        $is_renewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first('is_renewed');
        $terms = PolicyTerm::where('policy_id',$policy->id)->where('trans_type','RENEW')->where('status','Active')->orderBy('id','desc')->first();
        if (isset($is_renewal) && isset($terms) && $is_renewal->is_renewed == 1) {
            // $now = Carbon::now();
            // $term_date =  date('Y-m-d', strtotime($terms->term_end_date));

            $term_date=strtotime($terms->term_end_date);
            $term_date=\Carbon\Carbon::parse($term_date);

            $now=\Carbon\Carbon::now();
            $months = $term_date->diffInMonths($now);
            if($months == 3){
                $is_renewal->is_renewed = 0;
            }
        }


        // if($policy->product_id == 3){
        //     if($policy->policyActivatedDate != null){
        //         $days = Carbon::parse($policy->policyActivatedDate)->diffInDays(Carbon::now());
        //         if($days >= 274){
        //             $is_renewal = 1;
        //         }else{
        //             $is_renewal = 0;
        //         }
        //     }else{
        //         $payment = PaymentTransaction::where('policyNumber',$policy->policyNumber)->first(array('paymentDate'));
        //         if($payment){
        //             $days = Carbon::parse($payment->paymentDate)->diffInDays(Carbon::now());
        //             if($days >= 274){
        //                 $is_renewal = 1;
        //             }else{
        //                 $is_renewal = 0;
        //             }
        //         }else{
        //             $is_renewal = 0;
        //         }
        //     }
        // }else{
        //     $is_renewal = 0;
        // }

        /**** Old Vehicle data for reinstate */
         if($policy->trans_type="REINSTATE")
         {
             $old_vehicle_data_reinstant=\AlphaDirect\Models\vehicleOld::where('policy_id',$policy->id)->get();


         }else{
             $old_vehicle_data_reinstant=Null;
         }

            // For reinstate policy

        $date=\AlphaDirect\PolicyActivateCancelledDate::where('policyNumber',$policy->policyNumber)->first('cancelled_date');
        if($date != null)
        {

            $cancel_date=strtotime($date['cancelled_date']);
            //$cancel_date=Carbon::createFromFormat('Y-m-d', $cancel_date)->format('Y-m-d H:i:s');
           $cancel_date=\Carbon\Carbon::parse($cancel_date);

            $check=\Carbon\Carbon::now();
             $reinstate_days=$cancel_date->diffInDays($check);
            //$days=0;

        }else{
            $cancel_date=$policy->updated_at;
            if($cancel_date == NULL)
            {
                $reinstate_days=0;
            }else{
               // $cancel_date=\Carbon\Carbon::now();
                $check=\Carbon\Carbon::now();
                $reinstate_days=$cancel_date->diffInDays($check);
            }
        }

        $days_to_reinstate=\AlphaDirect\Lookup::where('key','days_to_resinstate')->first('value');
        $functionality='Reinstate';

        /** End for reinstate */


        $annual_Premium = null;
        $monthly_Premium = null;
        $three_Installment = null;
        $total_premium = null;

        if (isset($policy->premium) && isset($policy->premium_freq)) {

            if (isset($policy->annual_premium)) {
                $total_premium = round($policy->annual_premium,2);
            } else {
                $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
                $total_premium = round($total_premium['annual'],2);
            }

            // dd($total_premium);

            if ($policy->premium_freq == 1) {
                $monthly_Premium = $policy->premium;
            } else {
                $policyCon = new PolicyController();
                $premium = $policyCon->getMonthlyPrem(3,$total_premium);
                $monthly_Premium = round($premium,2);
            }

            if ($policy->premium_freq == 2) {
                $three_Installment = $policy->premium;
            } else {
                $premium = $total_premium / 3;
                $three_Installment = round($premium,2);
            }

            if ($policy->premium_freq == 3) {
                $annual_Premium = $policy->premium;
            } else {
                $annual_Premium = $total_premium;
            }
        }
        /**** End Old vehicle data ****/
        $policy_term = PolicyTerm::where('policy_id',$policy->id)->orderBy('id','desc')->get();

        $logged_in_agency_manager = false;
        if((Auth()->user()->hasRole('Agency Manager')) && (Auth()->user()->agency_id == $agency_id)) {
            $logged_in_agency_manager = true;
        }
        $PolicyBundled = PolicyBundled::where('policy_id',$policy->id)->get();

        $reratelogData = null;
        $motorcompData = null;
        if ($product->id == 3) {
            if ($policy->quoteNumber != null) {
                $motorcompData= MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();
                if (isset($motorcompData)) {
                    $reratelogData = PolicyPremiumReratingLog::where('ratings_id',$motorcompData->ratings_id)->orderBy('id','desc')->first();
                }
            }
        }


        $policyRenewalButton = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
        // dd($policyRenewalButton);


        $paymentDetails = PaymentTransaction::where('policyNumber',$policy->policyNumber)->get(array('id','referenceNumber','amount','paymentDate'));
        if (($policy->agent_id == auth()->user()->id) || $policy->agent_id == null || $logged_in_agency_manager == true|| auth::user()->hasPermissionTo('policy-Full List') || $policy->agent_id == '0') {
            if ($product->has_vehicle == 1) {
                return view('admin.policy.policyDetails_View', compact('newConvertedAmt','paymentDetails','reratelogData','scheduleTransactionCount','balanceArrears','EmailDocsPolicyBundled','policyRenewalButton','vehiclePolicyTyreRim','vehicleDetailsTyreRim','vehiclePurposeTyreRim','balance_fresh','policies_reinstate','balance_arrears','policyactivatecancelleddates','PolicyCoverages','annual_Premium','PolicyBundled','PolicyLedgers','three_Installment','monthly_Premium','years','policy_term','is_renewal','omang_passport_id','motorPreminum','quote_id','fileNames','feedback','coverNote', 'balance', 'cancelNote', 'dataModel', 'dataMake', 'reratedPremiumQuotes', 'premiumCalcDetails', 'passpostIssueCountry', 'storeName', 'emailDocs', 'vehicleMakes', 'vehicleModels', 'claims', 'productPlan', 'premium', 'count','policyFactors', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name', 'policyMotorItems', 'motor_items', 'transaction', 'policy_cellphone', 'updateDevices', 'banks','customerMati','matiDetails','matiVerifData','vehicleOld','old_vehicle_data_reinstant','reinstate_days','days_to_reinstate','functionality'));
            } else {
                return view('admin.policy.policyDetails_View', compact('newConvertedAmt','paymentDetails','reratelogData','scheduleTransactionCount','balanceArrears','EmailDocsPolicyBundled','policyRenewalButton','vehiclePolicyTyreRim','vehicleDetailsTyreRim','vehiclePurposeTyreRim','balance_fresh','policies_reinstate','balance_arrears','policyactivatecancelleddates','PolicyCoverages','annual_Premium','PolicyLedgers','PolicyBundled','three_Installment','monthly_Premium','years','policy_term','is_renewal','omang_passport_id','motorPreminum','quote_id','fileNames','feedback','coverNote','balance', 'cancelNote', 'passpostIssueCountry', 'storeName', 'emailDocs', 'claims', 'productPlan', 'premium', 'count','policyFactors', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name', 'policyMotorItems', 'motor_items', 'transaction', 'policy_cellphone', 'updateDevices', 'banks','customerMati','matiDetails','matiVerifData','vehicleOld','old_vehicle_data_reinstant','reinstate_days','days_to_reinstate','functionality'));
            }
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function getMotorComprehensivePolicyPremium($data){
        $existingPremium = $data['premium'];
        $existingPremFreq = $data['premium_freq'];
        $freqArr = array("1"=>"monthly","2"=>"3_inst","3"=>"annual");
        $premiumArr =  array();
        if($existingPremFreq == "1"){
            $monthlyPrem = $existingPremium;
        }else{
            $monthlyPrem = $this->getMonthlyPrem($existingPremFreq ,$existingPremium);
        }
        foreach($freqArr as $key=>$value){
            if($key == $existingPremFreq){
                $premiumArr[$value] = (double)$existingPremium;
            }else{
                switch($key){
                    case 1:
                        $newPremium = $monthlyPrem;
                        $premiumArr['monthly'] = $newPremium;
                        break;

                    case 2:
                        $newPremium = $monthlyPrem * 4;
                        $premiumArr['3_inst'] = $newPremium;
                        break;

                    case 3:
                        $newPremium = ($monthlyPrem * 12) / 1.08;
                        $premiumArr['annual'] = $newPremium;
                        break;

                        default:
                     break;
                }
            }
        }
        return $premiumArr;

    }

    public function getMotorCompPremiumForConvertingFreq($totalPremium,$newFrequency){
        $premiumArr = array();
        if($totalPremium == null && $newFrequency == null){
            return null;
        }

        $newPolicyPremium  = $totalPremium;
        $newPolicyPremFreq = $newFrequency;

        $freqArr = array("1"=>"monthly","2"=>"3_inst","3"=>"annual");

        foreach($freqArr as $key=>$value){
            switch($key){
                case 1:
                    $policyCon = new PolicyController();
                    $premium = $policyCon->getMonthlyPrem(3,$newPolicyPremium);
                    $premiumArr['monthlyPremium'] = round($premium,2);
                    break;

                case 2:
                    $premium = $newPolicyPremium / 3;
                    $premiumArr['threeInstlPremium'] = round($premium,2);
                    break;

                case 3:
                    $premiumArr['annualPremium'] = round($newPolicyPremium,2);
                    break;

                    default:
                    break;
            }
        }

        // if($newPolicyPremFreq == 2){
        //     $premium = $newPolicyPremium / 3;
        //     $premiumArr['threeInstlPremium'] = round($premium,2);
        // }
        // elseif($newPolicyPremFreq == 3){
        //     $premiumArr['annualPremium'] = $newPolicyPremium;
        // }
        // elseif($newPolicyPremFreq == 1){
        //     $policyCon = new PolicyController();
        //     $premium = $policyCon->getMonthlyPrem(3,$newPolicyPremium);
        //     $premiumArr['monthlyPremium'] = round($premium,2);
        // }

        return $premiumArr;

    }

    public function getMotorComprehensivePremium($policyNumber){
        if($policyNumber == null){
            return null;
        }
        $policy = Policy::where('policyNumber', $policyNumber)->first();

        $existingPremium  = $policy->premium;
        $existingPremFreq = $policy->premium_freq;
        $freqArr          = array("1"=>"monthly",   "2"=>"3_inst",  "3"=>"annual");
        $premiumArr       = array();

        if($existingPremFreq == "1"){
            $monthlyPrem = $existingPremium;
        }else{
            $monthlyPrem = $this->getMonthlyPrem($existingPremFreq ,$existingPremium);
        }

        foreach($freqArr as $key=>$value){
            if($key == $existingPremFreq){
                $premiumArr[$value] = (double)$existingPremium;
            }else{
                switch($key){
                    case 1:
                        $newPremium = $monthlyPrem;
                        $premiumArr['monthly'] = $newPremium;
                        break;

                    case 2:
                        $newPremium = $monthlyPrem * 4;
                        $premiumArr['3_inst'] = $newPremium;
                        break;

                    case 3:
                        $newPremium = ($monthlyPrem * 12) / 1.08;
                        $premiumArr['annual'] = $newPremium;
                        break;

                        default:
                     break;
                }
            }
        }
        return $premiumArr;

    }

    public function getMotorComprehensivePremiumDpo($policyNumber){
        if($policyNumber == null){
            return null;
        }
        $policy = Policy::where('policyNumber', $policyNumber)->first();

        $existingPremium  = $policy->premium;
        $existingPremFreq = $policy->premium_freq;
        $freqArr          = array("1"=>"monthly",   "2"=>"3_inst",  "3"=>"annual");
        $premiumArr       = array();

        if($existingPremFreq == "1"){
            $monthlyPrem = $existingPremium;
        }else{
            $monthlyPrem = $this->getMonthlyPrem($existingPremFreq ,$existingPremium);
        }

        foreach($freqArr as $key=>$value){
            if($key == $existingPremFreq){
                $premiumArr[$value] = (double)$existingPremium;
            }else{
                switch($key){
                    case 1:
                        $newPremium = $monthlyPrem;
                        $premiumArr['monthly'] = $newPremium;
                        break;

                    case 2:
                        $newPremium = $monthlyPrem * 4;
                        $premiumArr['3_inst'] = $newPremium;
                        break;

                    case 3:
                        $newPremium = ($monthlyPrem * 12) / 1.08;
                        $premiumArr['annual'] = $newPremium;
                        break;

                        default:
                     break;
                }
            }
        }
        return $premiumArr;

    }
    public function getMonthlyPrem($existingPremFreq,$existingPremium){
        if($existingPremFreq == 2){
            return ($existingPremium / 4 ) * 1.08;
        }
        if($existingPremFreq == 3){
            return ($existingPremium / 12 ) * 1.08;
        }
    }

    /*
    * checks sum assured amount allocated to policy
    * param: policy id
    */
    public function checkreserve_amount(Request $request)
    {
        $policy = Policy::where('id', $request->policyId)->first('sum_assured');
        if ($policy->sum_assured > $request->reserve_amount) {
            return response()->json(['error' => 0, 'sum_assured' => $policy->sum_assured]);
        } else {
            return response()->json(['error' => 1, 'sum_assured' => $policy->sum_assured]);
        }
    }

    /*
    * removes beneficary associated with policy
    * param: beneficary id
    */
    public function deleteBeneficiary(Request $request)
    {
        $check = PolicyBeneficiary::where('id', $request->get('id'))->count();
        if ($check) {
            $body = 'Are you sure you want to delete Policy Beneficiary ?';
            return response()->json(['status' => 'success', 'beneficiary_id' => $request->get('id'), 'body' => $body]);
        } else {
            $body = 'Sorry you can not delete data ';
            return response()->json(['status' => 'error', 'body' => $body]);
        }
    }

    /*
     * removes beneficary associated with policy
     * param: beneficary id
     */
    public function removeBeneficiary($id)
    {
        $result = PolicyBeneficiary::where('id', $id)->delete();
        return redirect()->back()->with('success', 'Policy beneficiary deleted Successfully');
    }

    public function checkProductType(Request $request)
    {
        $productType = ProductType::where('id', $request->get('productTypeId'))->first();
        if ($productType != null) {
            if ($productType->name == 'Instant') {
                return response()->json(['isInstant' => 'yes']);
            } else {
                return response()->json(['isInstant' => 'no']);
            }
        } else {
            return response()->json(['status' => 'error']);
        }
    }


    public function sendPolicyMailForTest()
    {
        $user = User::where('id', 1)->first();
        $data = new \stdClass();
        $data->user_id = $user->id;
        $data->hook = 'cancel_policy';
        $data->attachment = NULL;
        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
        $markdown = new MailTemplate($data);
        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
        event(new \AlphaDirect\Events\SendMail($user->email,$emailTemplate->subject,"",$html,NULL,['hook' => $data->hook]));
       // Mail::to($user->email)->send(new MailTemplate($data));
    }

    public function getInstalmentAmount($ref){
        $amount = RealpayContractInstallments::where('InstalmentReferenceNumber',$ref)->first(array('InstalmentAmount'));
        if($amount){
            return $amount->InstalmentAmount;
        }else{
            return null;
        }
    }

    public function updatePaymentTransactions($data)
    {
        try {
            $payTrans = PaymentTransaction::where('referenceNumber', $data['referenceNumber'])->first();
            //->where('status', $data['status'])

            if ($payTrans == null) {
                $payTrans = new PaymentTransaction();
            }
                $payTrans->policyNumber = $data['policyNumber'];
                $payTrans->policy_id = $data['policy_id'] ?? null;
                $payTrans->referenceNumber = $data['referenceNumber'];
                $payTrans->amount = $data['amount'];
                $payTrans->status = $data['status'];
                $payTrans->paymentDate = $data['paymentDate'];
                $payTrans->is_ledger = 0;
                $payTrans->paymentMethod = $data['paymentMethod']; //Realpay or VCS
                $payTrans->numberOfInstalmentsPaid = $data['numberOfInstalmentsPaid'];
                $payTrans->note = $data['note'];
                $payTrans->save();
                return true;
 //          }
//          else {
//                return false;
//            }
        } catch (Exception $e) {
            return false;
        }
    }


    public function generateLedger()
    {
    }

    public function transactionLogs($id)
    {

        $data = VcsNewTransaction::where('policyNumber', $id)
            ->get();

        return DataTables::of($data)
            ->editColumn('is_ledger', function ($data) {
                if ($data->is_ledger == 0)
                    return 'NO';
                if ($data->is_ledger == 1)
                    return 'YES';
                if ($data->is_ledger == null)
                    return 'N/A';
            })
            ->editColumn('status', function ($data) {
                if ($data->status == 'Success') {
                    $cred = '<span class="kt-font-bold kt-font-success">' . $data->status . '</span>';
                    return $cred;
                }
                if ($data->status == 'Failed') {
                    $cred = '<span class="kt-font-bold kt-font-danger">' . $data->status . '</span>';
                    return $cred;
                }
                if ($data->status == null) {
                    $cred = '<span class="kt-font-bold kt-font-info">Not Available</span>';
                    return $cred;
                }
            })
            ->editColumn('start_date', function ($data) {
                if ($data->start_date == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">N/A</span>';
                    return $cred;
                }
                if ($data->start_date)
                    return $data->start_date;
            })
            ->editColumn('settlement_Date', function ($data) {
                if ($data->settlement_Date == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">N/A</span>';
                    return $cred;
                }
                if ($data->settlement_Date)
                    return $data->settlement_Date;
            })
            ->editColumn('frequency', function ($data) {
                if ($data->frequency == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">N/A</span>';
                    return $cred;
                }
                if ($data->frequency)
                    return $data->frequency;
            })
            ->editColumn('occurrences', function ($data) {
                if ($data->occurrences == null) {
                    $cred = '<span class="kt-font-bold kt-font-primary">N/A</span>';
                    return $cred;
                }
                if ($data->occurrences)
                    return $data->occurrences;
            })

            ->rawColumns(['is_ledger', 'status', 'start_date', 'settlement_Date', 'frequency', 'occurrences'])
            ->make(true);
    }


    public function fetchAllFailedTransactions()
    {
        try {
            $policies = Policy::where('product_id', '!=', 3)->get(array('id', 'policyNumber', 'customer_id'));
            foreach ($policies as $key => $policy) {
                if ($policy->policyNumber) {
                    $trans = VcsNewTransaction::where('policyNumber', $policy->policyNumber)
                        ->orderBy('id', 'DESC')
                        ->first(array('id', 'policyNumber', 'status'));
                    if ($trans->status != 'Success') {
                        $customer = Customer::where('id', $policy->customer_id)->first(array('email'));
                        $data = new \stdClass();
                        $data->user_id = $policy->id;
                        $data->hook = 'pending_payment';
                        $data->customer_id = $policy->customer_id;
                        $data->attachment = null;
                        $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                        $markdown = new MailTemplate($data);
                        $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                        event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policies->policyNumber,'hook' => $data->hook]));

                    //    Mail::to($customer->email)->send(new MailTemplate($data));
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }
    public function getsentPolicyDocuments($id)
    {
        $doc = sentPolicyDocuments::where('policyNumber', $id)->get();
        return DataTables::of($doc)

            ->editColumn('created_at', function ($doc) {
                if ($doc->created_at != null)
                    return Carbon::parse($doc->created_at)->format('Y-m-d H:i');
                else
                    return '-';
            })
            ->editColumn('policy_id', function ($doc) {

                if ($doc->policyNumber)
                    return $doc->policyNumber;
                else
                    return 'N/A';
            })
            ->editColumn('sentBy', function ($doc) {
                if ($doc->sentBy != null) {
                    $cred = $doc->sentBy;
                } else {
                    $cred = 'N/A';
                }
                return $cred;
            })

            ->editColumn('documents', function ($doc) {
                if ($doc->documents != null) {
                    $docs = unserialize($doc->documents);
                    $d = '';
                    foreach ($docs as $key=>$doc){
                        $d .= '<a href ="' . \AlphaDirect\Helper::getCloudFrontURL($doc) . ' " target= "_blank">Document #' . ($key+1) .'</a><br>';
                    }
                } else {
                    $d = '-';
                }
                return $d;
            })

            ->rawColumns(['policy_id', 'sentBy','documents'])
            ->make(true);
    }

    public function getPolicyDocuments($id)
    {
        $doc = GetPolicyDocuments::where('policyNumber', $id)->get();
        return DataTables::of($doc)

            ->editColumn('file_name', function ($doc) {
                if ($doc->file_name != null)
                    return $doc->file_name;
                else
                    return 'NA';
            })
            ->editColumn('added_by', function ($doc) {
                if ($doc->added_by != null)
                    return $doc->added_by;
                else
                    return 'NA';
            })
            ->editColumn('created_at', function ($doc) {
                if ($doc->created_at != null)
                    return Carbon::parse($doc->created_at)->format('Y-m-d H:i');
                else
                    return 'NA';
            })
            ->editColumn('term_id', function ($doc) {
                if ($doc->term_id != null){
                    $termData = PolicyTerm::where('id',$doc->term_id)->first();
                    if($termData)
                        $term = Carbon::parse($termData->term_start_date)->year.'-'.Carbon::parse($termData->term_end_date)->year;
                    else
                        $term = '-';
                }else{
                    $term = '-';
                }

                return $term;
            })

            ->editColumn('doc_path', function ($doc) {
                if ($doc->doc_path != null) {
                    $images = '<a href="' .\AlphaDirect\Helper::getCloudFrontURL($doc->doc_path) . '" target="_blank">';

                    if (pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'pdf')
                    {
                        $images .=   '<img src="'.asset('images/pdf.ico').'" width="20%" height="auto">';
                        // $images .=   '<i class="fa fa-file" aria-hidden="true"></i>';
                    }elseif (pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'docx' || pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'doc' || pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'docm')
                    {
                        $images .=   '<img src="'.asset('images/word.ico').'" width="20%" height="auto">';
                    }elseif (pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'xls' || pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($doc->doc_path, PATHINFO_EXTENSION) == 'csv')
                    {
                        $images .=   '<img src="'.asset('images/excel.png').'" width="20%" height="auto">';
                    }else
                    {
                        $images .=   '<img src="'.asset('images/doc.png').'" width="20%" height="auto">';
                    }

                    $link = $images . '</a>';
                    return  $link;

                } else {
                    $images = '<img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="20%" height="auto" >';
                    return  $images;
                }
            })

            ->rawColumns(['file_name','term_id','added_by', 'created_at','doc_path'])
            ->make(true);
    }


    public function reSendPolicyDocument($policyId)
    {
        try {
            $policy = Policy::where('id', $policyId)->first();
            $product = Product::where('id', $policy->product->id)->first(array('id', 'has_schedule', 'has_wordings'));
            $customer = Customer::where('id', $policy->customer_id)->first(array('firstName', 'lastName', 'middleName', 'email', 'cellphone'));
            $policy_documents = PolicyDocuments::where('policy_id', $policyId)->first(array('doc_path'));
            $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));
            $attachments = array();
            if ($product->has_wordings == 1) {
                if ($docs != null) {
                    foreach ($docs as $doc) {
                        if ($doc->link)
                            array_push($attachments, $doc->link);
                    }
                }
            }

            if ($product->has_schedule == 1) {
                if ($policy->policyDocument != null) {
                    array_push($attachments, $policy->policyDocument);
                }else {
                    $document = new DocumentController();
                    $path = $document->generatePolicyDocument($policyId);
                    array_push($attachments, $path);
                }
            }

            if ($customer->email != null) {
                $data = new \stdClass();
                $data->user_id = $policy->id;
                $data->hook = 'send_policy_document';
                $data->customer_id = $policy->customer_id;
                $data->attachment = $attachments;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));

                return Redirect::back()->with('success', 'Documents sent successfully');
            } else {
                return Redirect::back()->with('error', 'Customer email address not found');
            }
        } catch (Exception $ex) {
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function sendPolicyDocument($policyId, $sentBy = 'Agent')
    {
        try {
            $policy = Policy::where('id', $policyId)->first();
            $product = Product::where('id', $policy->product_id)->first(array('id', 'has_schedule', 'has_wordings'));
            $customer = Customer::where('id', $policy->customer_id)->first(array('firstName', 'lastName', 'middleName', 'email', 'cellphone'));
            //$docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));
            $docsid = [];
            if(Documents::where('product_id', $policy->product_id)->where('status', 1)->where('plan_id', $policy->plan_id)->where('plan_id','!=',null)->exists()){
             $pid = Documents::where('product_id', $policy->product_id)->where('status', 1)
                                          ->where('plan_id', $policy->plan_id)->get(['id']);
            if(count($pid) > 0){
                foreach($pid as $id){
                    $docsid[] = $id->id;
                }
            }

            $pdi = Documents::where('product_id', -1)->where('status', 1)->get(['id']);
            if(count($pdi) > 0){
                foreach($pdi as $id2){
                    $docsid[] = $id2->id;
                }
            }


            $docs = Documents::whereIn('id', $docsid)->where('status', 1)->get(array('link'));
            }else{
                $docs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));
            }

            $attachments = array();
            if ($product->has_wordings == 1) {
                if ($docs != null) {
                    foreach ($docs as $doc) {
                        if ($doc->link)
                            array_push($attachments, $doc->link);
                    }
                }
            }
            if ($product->has_schedule == 1) {
                if ($policy->policyDocument != null) {
                    array_push($attachments, $policy->policyDocument);
                } else {
                    $document = new DocumentController();
                    $path = $document->generatePolicyDocument($policyId);
                    $newPolicy = Policy::where('id',$policy->id)->first();
                    if ($newPolicy->policyDocument != null) {
                        array_push($attachments, $newPolicy->policyDocument);
                    }
                }
            }
            //sms
            // $sms = new SmsMessaging();
            // $sms->sendSmsPolicyCreate(28, $policy->policyNumber, $customer->firstName, $customer->lastName, $customer->cellphone);

            if ($customer->email != null) {
                $data = new \stdClass();
                $data->user_id = $policy->id;
                $data->hook = 'send_policy_document';
                $data->customer_id = $policy->customer_id;
                $data->attachment = $attachments;

                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,$attachments,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));

                //$sent = Mail::to($customer->email)->send(new MailTemplate($data));
                $sentDocs = new sentPolicyDocumentLogs();
                $sentDocs->policyNumber = $policy->policyNumber;
                $sentDocs->email =  $customer->email;
                $sentDocs->sentBy = $sentBy;
                $sentDocs->doc = 'Policy Document';
                $sentDocs->documents = serialize($attachments);
                $sentDocs->save();
                $url = 'https://graphite.alphadirect.co.bw/api/sendPolicyDocumentOnWhatsApp/'.$policy->id;
                $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_HTTPHEADER => [
                            "Content-Type: application/json",
                            "Accept: application/json",
                        ],
                    ]);

                $response = curl_exec($curl);
                curl_close($curl);
                if ($sentBy == 'System') {
                    return true;
                }

                return Redirect::back()->with('success', 'Documents sent successfully');
            } else {
                return Redirect::back()->with('error', 'Customer email address not found');
            }
        } catch (Exception $ex) {
            if($sentBy == 'System') {
                    return true;
            }
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function regeneratePolicyDocument($id)
    {
        //try{
            $document = new DocumentController();
            $generate = $document->generatePolicyDocument($id);
            if($generate == true)
                return Redirect::back()->with('success', 'Updated policy documents have been created');
            else
                return Redirect::back()->with('error', 'Unable to generate policy document!');
        // }catch(\Exception $ex){
        //     return Redirect::back()->with('error', $ex->getMessage());
        // }
    }

    public function regenerateInformationDocument($id)
    {
        try{
            $policy = Policy::where('id', $id)->first();
            $d = new DocumentController();
            $verificationDoc = $d->generateInformationDocument($id);

            if ($verificationDoc != null) {
                $policy->verification_doc = $verificationDoc;
                $saved = $policy->save();
            }

            if($verificationDoc == true)
                return Redirect::back()->with('success', 'Information Document have been generated');
            else
                return Redirect::back()->with('error', 'Unable to generate Information Document !');
        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    //policy payment status start
    public function policyPaymentStatus()
    {
        //if (auth::user()->hasPermissionTo('policy-payment-status-lists')) {
        return view('admin.policyPaymentStatus.index');
        //} else {
        //    return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        //}
    }

    public function policyPaymentStatusData(Request $request)
    {
        $query = PolicyPaymentStatusDump::query();

        if ($request->policyStatus_filter != '-1') {
            $query->where('policy_status', $request->policyStatus_filter);
        }
        if ($request->balance_filter != '-1') {
            if ($request->balance_filter == 0) {
                $query->where('balance', '>', 0);
            }
            if ($request->balance_filter == 1) {
                $query->where('balance', '<', 0);
            }
            if ($request->balance_filter == 2) {
                $query->where('balance', '=', 0);
            }
        }
        $records = $query;
        return DataTables::of($records)
            ->addColumn('policyNumber', function ($item) {
                $policy = Policy::where('policyNumber', $item->policyNumber)->latest()->first(['id']);
                if ($item->policyNumber != null && $policy != null) {
                    return '<a href="' . route('admin.policy.edit', $policy->id) . '' . "?policyPaymentStatus='Yes'" . '">' . $item->policyNumber . '</a>';
                } else {
                    return  'NA';
                }
            })
            ->editColumn('premium', function ($item) {
                return $item->premium ? $item->premium : 'NA';
            })
            ->editColumn('premium_freq', function ($item) {
                $premium_frequency = 'NA';
                if ($item->premium_freq == 1 || $item->premium_freq == null) {
                    $premium_frequency = 'Monthly';
                }
                if ($item->premium_freq == 2) {
                    $premium_frequency = '3 Installment';
                }
                if ($item->premium_freq == 3) {
                    $premium_frequency = 'Annual';
                }
                return $premium_frequency;
            })
            ->editColumn('total_prem_due_customer', function ($item) {
                return isset($item->total_prem_due_customer) ? $item->total_prem_due_customer : '0.00';
            })
            ->editColumn('total_payment_paid_by_cust', function ($item) {
                return isset($item->total_payment_paid_by_cust) ? $item->total_payment_paid_by_cust : '0.00';
            })
            ->addColumn('balance', function ($item) {
                $balance = '0.00';
                if ($item->balance < 0) {
                    $balance = '<span class="kt-font-bold kt-font-danger">+' . str_replace('-', '', $item->balance) . '</span>';
                } else {
                    $balance = '<span class="kt-font-bold kt-font-success">-' . $item->balance . '</span>';
                }
                return $balance;
            })
            ->editColumn('failed_tx_count', function ($item) {
                return isset($item->failed_tx_count) ? $item->failed_tx_count : 0;
            })
            ->addColumn('policy_status', function ($item) {
                $status = '';
                if ($item['policy_status'] == 1) {
                    $status .= '<span class="kt-font-bold kt-font-brand">Activated</span>';
                } elseif ($item['policy_status'] == 2) {
                    $status .= '<span class="kt-font-bold kt-font-danger">Cancel</span>';
                } else {
                    $status .= '<span class="kt-font-bold kt-font-focus">Deactivated</span>';
                }

                return $status;
            })
            ->addColumn('action', function ($item) {
                $action = '-';
                if ($item->balance < 0) {
                    $action = '<a href="' . route('admin.sendPolicyPaymentSms', 'policy_payment_status_dump_id=' . $item->id) . '" ><i class="fa fa-comment" title="Send SMS"></i></a>';
                }
                return $action;
            })
            ->rawColumns(['policy_status', 'policyNumber', 'action', 'balance'])
            ->make(true);
    }

    public function sendPolicyPaymentSms(Request $request)
    {

        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            $query = PolicyPaymentStatusDump::where('balance', '<', 0)->get();
            if ($request->has('policy_payment_status_dump_id')) {
                $query = PolicyPaymentStatusDump::where('id', $request->policy_payment_status_dump_id)->get();
            }
            $sms_template = DB::table('sms_templates')->where('slug', 'payment_reminder_sms')->first();

            $record  = $query->each(function ($e) use ($sms_template) {
                $random_string = str_random(8);
                $url = config('services.pay.pay_url') . '?id=' . base64_encode($e->id) . '&uq=' . $random_string;
                $text = str_replace("[URL]", "<a href='$url'>Pay Now</a>", str_replace("[PAYMENT]", str_replace('-', '', $e->balance), html_entity_decode($sms_template->text)));
                $cellphone = Policy::with('customer')->where('policyNumber', $e->policyNumber)->first();
                $vcs = VcsNewTransaction::where([['policyNumber', $e->policyNumber], ['status', 'Failed']])->latest()->first();
                //$smsSendDate = Carbon::parse($vcs->created_at)->addDays($sms_template->which_day)->format('Y-m-d');
                // $smsSendEndDate = Carbon::parse($smsSendDate)->addDays($sms_template->sms_limit)->format('Y-m-d');
                // $period = CarbonPeriod::create($smsSendDate, $smsSendEndDate);
                //foreach ($period as $date) {
                //if($date->format('Y-m-d') == Carbon::now()->format('Y-m-d')){    //check current date and vcs tx date

                $smslog = SmsLogs::where('policyNumber', $e->policyNumber)->first();
                if ($smslog) {   //check policy number exist or not in smslog table
                    if ($smslog->sms_limit < $sms_template->sms_limit) { //check sms send limit
                        $smslog->sms_limit = $smslog->sms_limit + 1;
                        $smslog->policyNumber = $e->policyNumber;
                        $smslog->cellphone = isset($cellphone->customer->cellphone) ? $cellphone->customer->cellphone : '12345678';
                        $smslog->sms_template_id =  $sms_template->id;
                        $smslog->sms_status = 1;
                        $smslog->sms_text = $text;
                        $smslog->created_by = 290; // change to login user
                    } else {
                        $smslog->is_limit_send = 1;
                    }
                    $smslog->save();
                } else {
                    $data = new SmsLogs();
                    $data['policyNumber'] = $e->policyNumber;
                    $data['cellphone'] = isset($cellphone->customer->cellphone) ? $cellphone->customer->cellphone : '12345678';
                    $data['sms_template_id'] =  $sms_template->id;
                    $data['sms_status'] = 1;
                    $data['sms_text'] = $text;
                    $data['created_by'] = 290;
                    $data['sms_limit'] = 1;
                    $data->save();
                }

                //store url in payment_url table
                $paymentUrl = new PaymentUrls();
                $paymentUrl->uniq_code = $random_string;
                $paymentUrl->cellphone = $e->policyNumber;
                $paymentUrl->policy_id = $cellphone->id; //policy_id
                $paymentUrl->amount = str_replace('-', '', $e->balance);
                $paymentUrl->status = 0;
                $paymentUrl->url = $url;
                $paymentUrl->request_from = 'graphite';
                $paymentUrl->created_by = 1;
                $paymentUrl->save();

                //}
                // }
            });
            \Illuminate\Support\Facades\DB::commit();
            if ($request->has('policy_payment_status_dump_id')) {
                return redirect()->back()->with('success', 'SMS send successfully');
            } else {
                return 'success';
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return $e->getMessage();
        }
    }


    public function payamentStatusDumpData()
    {
        ini_set('max_execution_time', 0);
        try {
            $records = Policy::select('id', 'policyNumber', 'status', 'premium as one_month_premium', 'policyNumber', 'created_at', 'status as policy_status', 'billingStartDate', 'product_id', 'premium_freq')
                ->where('billingStartDate', '!=', null)
                ->get()->chunk(100);
            foreach ($records as $record) {
                $arr = [];
                foreach ($record as $item) {
                    $ifExists = PolicyPaymentStatusDump::where('policyNumber', $item->policyNumber)->first();
                    $total_prem_due_customer = PaymentTransaction::where('policyNumber', $item->policyNumber)
                        ->where('amount', '!=', '1.00')
                        ->where(function ($query) {
                            $query->where('status', '=', 'Failed')
                                ->orWhere('status', '=', 'F');
                        })
                        ->sum('amount');

                    $total_payment_paid_by_cust =  PaymentTransaction::where('policyNumber', $item->policyNumber)
                        ->where('amount', '!=', '1.00')
                        ->where(function ($query) {
                            $query->where('status', '=', 'Success')
                                ->orWhere('status', '=', 'S');
                        })
                        ->sum('amount');
                    $balance = $total_payment_paid_by_cust - $total_prem_due_customer;
                    $failed_tx_count = PaymentTransaction::where('policyNumber', $item->policyNumber)
                        ->where('amount', '!=', '1.00')
                        ->where(function ($query) {
                            $query->where('status', '=', 'Failed')
                                ->orWhere('status', '=', 'F');
                        })->count();
                    if ($ifExists == null) {
                        $data = array();
                        $data['policyNumber'] = $item->policyNumber;
                        $data['premium'] = $item->one_month_premium;
                        $data['premium_freq'] = $item->premium_freq;
                        $data['total_prem_due_customer'] = $total_prem_due_customer;
                        $data['total_payment_paid_by_cust'] = $total_payment_paid_by_cust;
                        $data['balance'] = $balance;
                        $data['failed_tx_count'] = $failed_tx_count;
                        $data['policy_status'] = $item->status;
                        array_push($arr, $data);
                    } else {
                        $ifExists->total_prem_due_customer = $total_prem_due_customer;
                        $ifExists->total_payment_paid_by_cust = $total_payment_paid_by_cust;
                        $ifExists->balance = $balance;
                        $ifExists->failed_tx_count = $failed_tx_count;
                        $ifExists->policy_status = $item->status;
                        $ifExists->save();
                    }
                }
                DB::table('policy_payment_status_dump')->insert($arr);
            }

            return 'success';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }


    private function total_premium($item)
    {
        if (str_contains($item->billingStartDate, '/')) {
            $billingStartDate1 = Carbon::createFromFormat('d/m/Y', $item->billingStartDate)/*Carbon::parse($item->billingStartDate)*/;
            $billingStartDate = Carbon::parse($billingStartDate1);
        } else {
            $billingStartDate = Carbon::parse($item->billingStartDate);
        }

        $currentMonth = Carbon::now();
        if ($item->premium_freq == 2  && $item->product_id == 3) {      //for 3 installment
            $diffYr = $billingStartDate->diffInYears($currentMonth);
            $diffMonth = $billingStartDate->diffInMonths($currentMonth);
            $default  = 3;
            if ($diffMonth < $default && $diffYr > 0) {
                $diff = $diffMonth * $diffYr;
            } else {
                $diff = $default * $diffYr;
            }
        } elseif ($item->premium_freq == 3 && $item->product_id == 3) {  //for year wise
            $diff = $billingStartDate->diffInYears($currentMonth);
        } else {

            $diff = $billingStartDate->diffInMonths($currentMonth);
        }
        $total_prem = isset($item->one_month_premium) ? $item->one_month_premium : '0.00';
        if ($diff > '0.00') {
            $total_prem = $item->one_month_premium * $diff;
        }
        return $total_prem;
    }

    public function uploadKycImages(Request $request, $policy_id)
    {


        //KYC Update
        \Illuminate\Support\Facades\DB::beginTransaction();
        $policy = Policy::where('id', $policy_id)->first();


        try {
            if ($policy) {
                $user = Customer::where('id', $policy->customer_id)->first();
                $omangKYC = KYC::where('customer_id', $policy->customer_id)->first();
                if($omangKYC == null){
                    $omangKYC= new KYC();
                }

                if ($request->hasFile('driving_license')) {
                    $file = $request->file('driving_license');
                    if($file != null)
                    {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/driving_license' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $omangKYC->driving_license = $filePath;
                    }

                }
                if ($request->hasFile('omang_pic')) {
                    $file = $request->file('omang_pic');
                    if($file != null)
                    {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/Omang' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $omangKYC->omang = $filePath;
                    }
                }
                if ($request->hasFile('omangBack')) {
                    $file = $request->file('omangBack');
                    if($file != null)
                    {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/omangBack' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $omangKYC->omangBack = $filePath;
                    }
                }
                if ($request->hasFile('proof_residence')) {
                    $file = $request->file('proof_residence');
                    if($file != null)
                    {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/proof_residence' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $omangKYC->proof_residence = $filePath;
                    }
                }
                if ($request->hasFile('proof_income')) {
                    $file = $request->file('proof_income');
                    if($file != null)
                    {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/proof_income' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $omangKYC->proof_income = $filePath;
                    }
                }
                if ($request->hasFile('passport_pic')) {
                    $file = $request->file('passport_pic');
                   if($file != null)
                   {
                        $name = $file->getClientOriginalName();
                        $filePath = 'MIS/' . $policy->customer_id . '/' . 'KYC' . '/' . $user->firstName . '/passport' . '/' . $name;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $omangKYC->passport = $filePath;
                   }
                }
                if ($omangKYC->driving_license || $omangKYC->proof_residence || $omangKYC->proof_income || ($omangKYC->omang || $omangKYC->passport)) {
                    if (Auth::check()) {
                        activity('KYC Document')
                            ->performedOn($policy)
                            ->causedBy(User::where('id', auth()->user()->id)->first())
                            ->log('KYC document uploaded');
                    }
                }
                $omangKYC->save();

                \Illuminate\Support\Facades\DB::commit();
                return redirect()->back()->with('success', 'Kyc Images uploaded successfully');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function uploadVehicleImages(Request $request, $policy_id)
    {
        $current_date_time = \Carbon\Carbon::now()->toDateTimeString();


        \Illuminate\Support\Facades\DB::beginTransaction();
        $policy = Policy::where('id', $policy_id)->first();
        $vehicle = Vehicle::where('policy_id', $policy->id)->get();
        $vehicleOld1 = vehicleOld::where('policy_id', $policy->id)->first();
        foreach($vehicle as $vehicle);
        // dd($vehicleOld1);
          if($vehicleOld1 == null){
            $vehicleOld = new vehicleOld();
           $vehicleOld->customer_id = $vehicle->customer_id;

            $vehicleOld->policy_id = $vehicle->policy_id;

          if($vehicle->front > null){
            $vehicleOld->front = $vehicle->front;

          }
          if($vehicle->back > null){
            $vehicleOld->back = $vehicle->back;
          }
          if($vehicle->left > null){
            $vehicleOld->left = $vehicle->left;
          }

          if($vehicle->right > null){

            $vehicleOld->right = $vehicle->right;
         }
          if($vehicle->vehicleRegistration > null){
            $vehicleOld->vehicleRegistration = $vehicle->vehicleRegistration;
          }
          if($vehicle->vehicle_valuation > null){
            $vehicleOld->vehicle_valuation = $vehicle->vehicle_valuation;
          }

          if (isset($request->term_id)) {
            $vehicleOld->term_id = $request->term_id;
          }

          $vehicleOld->save();

        }else{
            $vehicleOld = vehicleOld::where('policy_id', $policy->id)->first();


          if($vehicle->front > null){
            $vehicleOld->front = $vehicle->front;

          }
          if($vehicle->back > null){
            $vehicleOld->back = $vehicle->back;
          }
          if($vehicle->left > null){
            $vehicleOld->left = $vehicle->left;
          }

          if($vehicle->right > null){

            $vehicleOld->right = $vehicle->right;
         }
          if($vehicle->vehicleRegistration > null){
            $vehicleOld->vehicleRegistration = $vehicle->vehicleRegistration;
          }
          if($vehicle->vehicle_valuation > null){
            $vehicleOld->vehicle_valuation = $vehicle->vehicle_valuation;
          }

          if (isset($request->term_id)) {
            $vehicleOld->term_id = $request->term_id;
          }

          $vehicleOld->save();
        }

        try {
            if ($vehicle) {
                if ($request->hasFile('front')) {
                    $file = $request->file('front');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/front' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->front = $filePath;
                }
                if ($request->hasFile('back')) {
                    $file = $request->file('back');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/back' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->back = $filePath;
                }
                if ($request->hasFile('right')) {
                    $file = $request->file('right');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/right' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->right = $filePath;
                }
                if ($request->hasFile('left')) {
                    $file = $request->file('left');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'Vehicle' . '/left' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->left = $filePath;
                }
                if ($request->hasFile('vehicleRegistration')) {
                    $file = $request->file('vehicleRegistration');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicleRegistration' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicleRegistration = $filePath;
                }
                if ($request->hasFile('vehicle_valuation')) {
                    $file = $request->file('vehicle_valuation');
                    $name = $file->getClientOriginalName();
                    $filePath = 'MIS/' . $policy->customer_id . '/' . 'vehicle_valuation' . '/' . $name;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $vehicle->vehicle_valuation = $filePath;
                }

                if($vehicle->status == 2)
                    $vehicle->status = 3;

                $saved = $vehicle->save();

                \Illuminate\Support\Facades\DB::commit();
                return redirect()->back()->with('success', 'Vehicle Images uploaded successfully');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


    public function getOldVehicleImages($policy_id,$term_id)
    {

        $policy = Policy::where('id', $policy_id)->first();
        $vehicle = Vehicle::where('policy_id', $policy->id)->first();

        // $vehicleOld1 = vehicleOld::where('policy_id', $policy->id)->first();

            // if($vehicleOld1 == null){
                $vehicleOld = new vehicleOld();
                $vehicleOld->customer_id = $vehicle->customer_id;

                $vehicleOld->policy_id = $vehicle->policy_id;

                if($vehicle->front > null){
                    $vehicleOld->front = $vehicle->front;

                }

                if($vehicle->back > null){
                    $vehicleOld->back = $vehicle->back;
                }
                if($vehicle->left > null){
                    $vehicleOld->left = $vehicle->left;
                }

                if($vehicle->right > null){

                    $vehicleOld->right = $vehicle->right;
                }
                if($vehicle->vehicleRegistration > null){
                    $vehicleOld->vehicleRegistration = $vehicle->vehicleRegistration;
                }
                if($vehicle->vehicle_valuation > null){
                    $vehicleOld->vehicle_valuation = $vehicle->vehicle_valuation;
                }

                if (isset($term_id)) {
                    $vehicleOld->term_id = $term_id;
                }


                $saved = $vehicleOld->save();

                return $saved;

            // }
            // else{
            //     $vehicleOld = vehicleOld::where('policy_id', $policy->id)->first();

            //     if($vehicle->front > null){
            //         $vehicleOld->front = $vehicle->front;

            //     }
            //     if($vehicle->back > null){
            //         $vehicleOld->back = $vehicle->back;
            //     }
            //     if($vehicle->left > null){
            //         $vehicleOld->left = $vehicle->left;
            //     }

            //     if($vehicle->right > null){

            //         $vehicleOld->right = $vehicle->right;
            //     }
            //     if($vehicle->vehicleRegistration > null){
            //         $vehicleOld->vehicleRegistration = $vehicle->vehicleRegistration;
            //     }
            //     if($vehicle->vehicle_valuation > null){
            //         $vehicleOld->vehicle_valuation = $vehicle->vehicle_valuation;
            //     }

            //     if (isset($term_id)) {
            //         $vehicleOld->term_id = $term_id;
            //     }

            //     $saved = $vehicleOld->save();

            //     return $saved;
            // }
    }

    public function updatePolicyNotes(Request $request, $policy_id)
    {
        $policy = Policy::policy($policy_id)->first(); //policy()  it is a scope function defined Polciy Model
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            if ($policy) {
                $policy->note = $request->note;
                $saved = $policy->save();

                \Illuminate\Support\Facades\DB::commit();
                return redirect()->back()->with('success', 'Notes updated successfully');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function payamentStatusDumpDataExport(Request $request)
    {
        return Excel::download(new PolicyPaymentStatusDumpExport($request->all()), 'PolicyPaymentStatusDumpExport.xlsx');
    }

    public function clientAuth()
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('REALPAY_BASE_URL') . "/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic " . env('CLIENT_AUTH'),
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return json_decode($response, true);
        } catch (\PHPUnit\Exception $e) {
            return response()->json(['Status' => 'Failed', 'Description' => $e->getMessage()], 401);
        }
    }

    public  function updatePayXwithRealPay()
    {
        #$payX =  PaymentTransaction::where('paymentMethod' ,'RealPay')->where('policyNumber','LIKE',"%MIS%")->get();
        $payX =  PaymentTransaction::where('paymentMethod', 'RealPay')->where('policyNumber', 'LIKE', "%MIS%")->groupBy('policyNumber')->get();
        foreach ($payX as $payXrow) {
            $RPinstallments = realpayContractInstallments::wherein('InstalmentStatus', ['F', 'S'])->where('clientNumber', $payXrow->policyNumber)->orderBy('InstalmentReferenceNumber', 'ASC')->get();

            PaymentTransaction::where('policyNumber', $payXrow->policyNumber)->delete();
            $installments = 0;
            foreach ($RPinstallments as $RPinstallmentRow) {
                $newPayXRow = new PaymentTransaction;
                $newPayXRow->policyNumber = $RPinstallmentRow->clientNumber;
                $newPayXRow->paymentMethod = "RealPay";
                $newPayXRow->referenceNumber = $RPinstallmentRow->InstalmentReferenceNumber;
                $newPayXRow->amount = $RPinstallmentRow->InstalmentAmount;
                $newPayXRow->status = $RPinstallmentRow->InstalmentStatus == 'S' ? 'Success' : 'Failed';
                if ($RPinstallmentRow->InstalmentStatus == 'S') {
                    $installments++;
                }
                $newPayXRow->numberOfInstalmentsPaid = $installments;
                $newPayXRow->amount = $RPinstallmentRow->InstalmentAmount;
                $newPayXRow->note = $RPinstallmentRow->instalmentResponse;
                $newPayXRow->paymentDate = date('Y-m-d', strtotime($RPinstallmentRow->InstalmentActionDate));
                $newPayXRow->save();
            }
        }
    }

    public function storeCashPayment(Request $request)
    {
        try{
            if (auth::user()->hasPermissionTo('offline-payments-list') || ('offline-payments-edit')) {

                try {
                    //            \Illuminate\Support\Facades\DB::beginTransaction();

//                $validator = Validator::make($request->all(), [
//                    'paymentDate' => 'required',
//                    'paymentAmount' => 'required',
//                    'policyNumber' => 'required',
//                    'receiptNumber' => 'required',
//                    'billingCell' => 'required',
//                    'paymentFreq' => 'required',
//                    'paymentNote' => 'required',
//                    'RPBanks' => 'required_if:addRealpay,1',
//                    'RPBankBranch' => 'required_if:addRealpay,1',
//                    'accountType' => 'required_if:addRealpay,1',
//                    'paymentStartDate' => 'required_if:addRealpay,1',
//                    'accountNumber' => 'required_if:addRealpay,1',
//                    'numberOfInstalmentsPaid' => 'required',
//                ]);
//                if ($validator->fails()) {
//                    return redirect()->back()->with('error', 'All fields are mandatory');
//                }
                    $policy = Policy::where('policyNumber', $request->policyNumber)->first(array('id', 'policyNumber', 'customer_id', 'status', 'quoteNumber'));
                    if ($policy->status != 1) {
                        $update = $this->updatePolicyDates($policy->policyNumber, 1);
                    }
                    //$customer = Customer::where('id',$policy->customer_id)->first(array('cellphone'));
                    $entry = new PaymentTransaction();
                    $entry->policyNumber = $request->policyNumber;
                    $entry->referenceNumber = Carbon::now()->timestamp.'/'.$request->receiptNumber;
                    $entry->amount = $request->paymentAmount;
                    $entry->status = 'SUCCESS';
                    $entry->paymentDate = $request->paymentDate;
                    $entry->paymentMethod = 'CASH';
                    $entry->is_ledger = 0;
                    $entry->cashRecipient = $request->paymentRecievedBy;
                    $entry->paymentFrequency = $request->paymentFreq;
                    $entry->numberOfInstalmentsPaid = $request->numberOfInstalmentsPaid;
                    $entry->note = $request->paymentNote;
                    $entry->paymentLoggedBy = auth()->user()->id;
                    if ($request->hasFile('payment_image')) {
                        $file = $request->file('payment_image');
                        $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
                        $filePath = 'PolicyPayment/' . $request->policyNumber . '-' . $entry->referenceNumber;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $entry->payment_proof_link = $filePath;
                    }
                    $entry->save();
                    if ($entry->save()) {
                        $check = CustomerBanking::where('policy_id', $policy->id)->first();
                        if ($check == null) {
                            $banking = new CustomerBanking();
                        } else {
                            $banking = CustomerBanking::where('policy_id', $policy->id)->first();
                        }
                        $banking->customer_id = $policy->customer_id;
                        $banking->policy_id = $policy->id;
                        if (isset($request->addRealpay) && $request->addRealpay == 1)
                            $banking->billing = "RealPay";
                        $banking->billingCell = $request->billingCell;
                        $banking->bankName = $request->RPBanks;
                        $banking->branchCode = $request->RPBankBranch;
                        $banking->accountType = $request->accountType;
                        if($banking->billingStartDate == null){
                        $banking->billingStartDate = $request->paymentStartDate;
                        }
                        $banking->accountNumber = $request->accountNumber;
                        if($banking->billing_day == null){
                        $banking->billing_day = date("d", strtotime($request->paymentStartDate));
                        }
                        $banking->save();

                        if ($policy->quoteNumber != null) {
                            $compQuote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();

                            if ($request->paymentFreq != null && $compQuote != null) {
                                if ($request->paymentFreq == 1) {
                                    $premium = $compQuote->premiumMonthly;
                                } elseif ($request->paymentFreq == 2) {
                                    $premium = $compQuote->premium3Inst;
                                } elseif ($request->paymentFreq == 3) {
                                    $premium = $compQuote->premiumAnnually;
                                } else {
                                    //                            \Illuminate\Support\Facades\DB::rollBack();
                                    return redirect()->back()->with('error', 'Premium not found in Quote as per selected frequency');
                                }
                                //$policy->billingStartDate = $request->paymentStartDate; Commented by Sanket
                                $policy->billing_day = $banking->billing_day;
                                $policy->premium = $premium;
                                $policy->status = 1;
                                $policy->premium_freq = $request->paymentFreq;
                                //$policy->policyActivatedDate =  Carbon::now()->format("Y-m-d");
                                $saved = $policy->save();


                            } else {
                                //                        \Illuminate\Support\Facades\DB::rollBack();
                                return redirect()->back()->with('error', 'Payment frequency found null.');
                            }
                        } else {
                            //                    \Illuminate\Support\Facades\DB::rollBack();
                            return redirect()->back()->with('error', 'Quote number not found for this policy');
                        }

                        if (isset($request->addRealpay) && $request->addRealpay == 1 && $request->accountNumber  && $request->RPBanks  && $request->RPBankBranch  && $request->accountType) {
                            $realpay = RealpayLogs::where('policy_id', $policy->id)->orderBy('id', 'DESC')
                                ->where('status', 1)
                                ->first();
                            if ($realpay != null) {
                                //                        \Illuminate\Support\Facades\DB::rollBack();
                                return redirect()->back()->with('error', 'Payment is already present on realpay');
                            }
                            $policy = Policy::where('id', $policy->id)->first();
                            $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
                            $profile = CustomerProfile::where('customer_id', $customer->id)->first();
                            $customerBanking = CustomerBanking::where('customer_id', $policy->customer_id)->orderBy('id', 'DESC')->first();
                            $fetchToken = $this->clientAuth();
                            if ($fetchToken['token_type'] && $fetchToken['access_token'])
                                $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                            else
                                return redirect()->back()->with('error', 'Problem generating AUTH() token');

                            if ($profile->omang != null) {
                                $id = $profile->omang;
                                $idType = 'I';
                            } else {
                                $id = $profile->passport;
                                $idType = 'P';
                            }
                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/clients/" . env('REALPAY_PRODUCT') . "?BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => "{\r\n  \"ClientPostRequest\": [\r\n    {\r\n
                                \"ClientNumber\": \"$policy->policyNumber\",\r\n
                                \"ClientName\": \"$customer->firstName $customer->lastName\",\r\n
                                \"IDType\": \"$idType\",\r\n
                                \"IDNumber\": \"$id\",\r\n
                                \"CellphoneNumber\": \"$customer->cellphone\",\r\n
                                \"EMail\": \"$customer->email\",\r\n
                                \"BankCode\": \"$banking->bankName\",\r\n
                                \"BranchCode\": \"$banking->branchCode\",\r\n
                                \"AccountType\": \"$banking->accountType\",\r\n
                                \"AccountNumber\": \"$banking->accountNumber\",\r\n
                                \"AccountHolderName\": \"$customer->firstName $customer->lastName\",\r\n
                                \"EmployeeGroupCode\": \"OT\",\r\n
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
                            $data = json_decode($response, true);
                            curl_close($curl);

                            //dd($customer->cellphone,$customer->email,$customerBanking->bankName,$customerBanking->branchCode,$customerBanking->accountType,$customerBanking->accountNumber,$customer->firstName.' '.$customer->lastName);

                            $rSeq = $data['APIResponse']['CallSequence'];

                            if (!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])) {
                                $fetchToken = $this->clientAuth();
                                if ($fetchToken['token_type'] && $fetchToken['access_token'])
                                    $token = $fetchToken['token_type'] . ' ' . $fetchToken['access_token'];
                                else
                                    return null;

                                $policy = Policy::where('id', $policy->id)->first();

                                if ($policy->product_id != 3)
                                    $policy->first_premium_wvat = 0;
                                $firstBillingDate = '';
                                $firstCollectionAmount = '';
                                $numberOfInstallments = '99';
                                $frequency = 'MNTH';
                                if ($policy->premium_freq == 1 && $policy->first_premium_wvat > 0) {
                                    $premium = $policy->premium;
                                    $now = new DateTime();
                                    $firstBillingDate = $now->format('Y-m-d');
                                    $firstCollectionAmount = $policy->first_premium_wvat;
                                    $numberOfInstallments = '99';
                                }
                                if ($policy->premium_freq == 1 && $policy->first_premium_wvat == 0) {
                                    $premium = $policy->premium;
                                }
                                if ($policy->premium_freq != 1 && ($policy->premium > 0 || $policy->premium != null) && $policy->billingStartDate) {
                                    $premium = $policy->premium;
                                    $now = new DateTime();
                                    if($policy->billingStartDate == null){
                                    $policy->billingStartDate = $now->format('Y-m-d');
                                    }
                                    if ($policy->premium_freq == 2) {
                                        $numberOfInstallments = '2';
                                    } elseif ($policy->premium_freq == 3) {
                                        $frequency = 'YEAR';
                                        $numberOfInstallments = '99';
                                    } elseif ($policy->premium_freq == 1) {
                                        $numberOfInstallments = '99';
                                    } else {
                                        if ($policy->quoteNumber) {
                                            $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                                            $premium = $quote->premiumMonthly;
                                            $numberOfInstallments = '99';
                                            $policy->premium_freq = 1;
                                            $saved = $policy->save();
                                        } else {
                                            //                                    \Illuminate\Support\Facades\DB::rollBack();
                                            return null;
                                        }
                                    }
                                }
                                if ($policy->billing_day == 31) {
                                    $policy->billing_day = 99;
                                }
                                if($policy->billingStartDate == null){
                                $policy->billingStartDate = $request->paymentStartDate;
                                }
                                $contractNumber = RealpayClientContracts::getContractNumber($policy->id);
                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env('REALPAY_BASE_URL') . "/maintain/contracts/" . env('REALPAY_PRODUCT') . "?BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => "",
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => "POST",
                                    CURLOPT_POSTFIELDS => "{\r\n
                        \"ContractPostRequest\": [\r\n
                            {\r\n
                                \"ClientNumber\": \"$policy->policyNumber\",\r\n
                                \"ContractNumber\": \"$contractNumber\",\r\n
                                \"FrequencyCode\": \"$frequency\",\r\n
                                \"CollectionDay\": \"$policy->billing_day\",\r\n
                                \"TrackingCode\": \"44\",\r\n
                                \"FirstCollectionDate\": \"$policy->billingStartDate\",\r\n
                                \"FirstCollectionAmount\": \"$policy->premium\",\r\n
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
                                if (sizeof($data['ContractPostResponse'][0]['Successful']) > 0 && sizeof($data['ContractPostResponse'][0]['Failed']) == 0) {
                                    $insData = $data['ContractPostResponse'][0]['Successful'][0];
                                    $logs = RealpayLogs::where('policy_id', $insData['ContractNumber'])
                                        ->where('event', 1)
                                        ->first();

                                    if ($logs == null) {
                                        $addLog = new RealpayLogs();
                                    } else {
                                        $addLog = RealpayLogs::where('policy_id', $insData['ContractNumber'])
                                            ->where('event', 1)
                                            ->first();
                                    }

                                    $addLog->policy_id = $insData['ContractNumber'];
                                    $addLog->event = 1;
                                    $addLog->status = 1;
                                    $addLog->save();

                                    $paymentReq = RealpayPaymentRequest::where('policy_id', $insData['ContractNumber'])->first();

                                    if ($paymentReq == null)
                                        $paymentReq = new RealpayPaymentRequest();

                                    $paymentReq->policy_id = $insData['ContractNumber'];
                                    $paymentReq->clientNumber = $insData['ClientNumber'];
                                    $paymentReq->client_response_sequence = $rSeq;
                                    $paymentReq->clientCreated = 1;
                                    $paymentReq->contractCreated = 1;
                                    $paymentReq->first_premium = $policy->first_premium_wvat;
                                    $paymentReq->premium = $policy->premium;
                                    $paymentReq->billing_day = $insData['CollectionDay'];
                                    $paymentReq->billing_date = $insData['FirstCollectionDate'];
                                    $paymentReq->contract = $insData['ContractNumber'];
                                    $paymentReq->contract_response_sequence = $data['APIResponse']['CallSequence'];
                                    $paymentReq->frequency = $policy->premium_freq;
                                    $paymentReq->response = 1;
                                    $paymentReq->status = 1;
                                    $paymentReq->save();

                                    $RealpayController = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                    $contract = $RealpayController->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                                    $installments = $RealpayController->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
                                    $policy->status = 1;
                                    $saved = $policy->save();

                                    $logData = [
                                        'policy_id'=>$policy->id,
                                        'client_number'=>$policy->policyNumber,
                                        'contract_number'=>$contractNumber,
                                        'status'=>1,
                                    ];
                                    $addLog = RealpayClientContracts::addLog($logData);

                                    if ($contract == true && $installments == true) {
                                        //                                \Illuminate\Support\Facades\DB::commit();
                                        return redirect()->back()->with('success', 'Client and contract added successfully on Realpay');
                                    } else {
                                        //                                \Illuminate\Support\Facades\DB::rollBack();
                                        return redirect()->back()->with('error', 'Problem storing contract data');
                                    }
                                } else {
                                    //                            \Illuminate\Support\Facades\DB::rollBack();
                                    return redirect()->back()->with('error', 'Problem adding realpay contract');
                                }
                            } else {
                                //                        \Illuminate\Support\Facades\DB::rollBack();
                                foreach ($data['ClientPostResponse'][0]['Failed'][0]['Failures'] as $f) {
                                    $err = new OfflinePaymentError();
                                    $err->policyNumber = $policy->policyNumber;
                                    $err->error_logged = $f['FailureDescription'];
                                    $err->save();
                                }
                                return redirect()->back()->with('error', 'Problem adding payment on realpay');
                            }
                        } else {
                            //                    \Illuminate\Support\Facades\DB::commit();
                            return redirect()->back()->with('success', 'Entry added without realpay payment');
                        }
                    } else {
                        //                \Illuminate\Support\Facades\DB::rollBack();
                        return redirect()->back()->with('error', 'Problem storing data in payment transaction table');
                    }
                } catch (Exception $e) {
                    //            \Illuminate\Support\Facades\DB::rollBack();
                    return redirect()->back()->with('error', $e->getMessage());
                }
            } else {
                return redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
            }
        }catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage().'-'.$e->getLine());
        }
    }

    public function addDiscountSurcharge(Request $request, $policyNumber)
    {
        try {

            $policy = Policy::where('policyNumber', $policyNumber)->first(array('id', 'sum_assured'));
            $setting = QuoteSettings::first(array('edit_limit'));
            $edited = PolicyPremiumReratingLog::where('policy_number', $policyNumber)
                ->where('discount', '!=', 'null')
                ->where('surcharge', '!=', 'null')
                ->get()
                ->count();

            if (((int)$setting->edit_limit >= $edited + 1) == true) {
                $requestData = $request->all();
                foreach ($requestData as $key => $req) {
                    if ($req == null || $req == '') {
                        return array('success'=>0,'message'=>ucfirst($key) . ' is required');
                        //return Redirect::back()->with('error', ucfirst($key) . ' is required');
                    }
                }

                \Illuminate\Support\Facades\DB::beginTransaction();
                $rerate_log = PolicyPremiumReratingLog::where('ratings_id', $request->rateID)
                    ->orderBy('id', 'DESC')
                    ->first();

                /*Start*/
                $data = PolicyPremiumReratingLog::where('policy_number', $policyNumber);
                $totalDisc = abs($data->where('discount', '!=', null)->sum('discount'));
                $totalDisc = number_format((float)$totalDisc, 2, '.', '');
                $totalSurc = PolicyPremiumReratingLog::where('policy_number', $policyNumber)->where('surcharge', '!=', null)->sum('surcharge');
                $totalSurcValue = number_format((float)$totalSurc, 2, '.', '');
                /*End*/

                //$old_value = $rerate_log->annual_ins;

                $Role = auth()->user()::with('roles')->first();
                $userRole = $Role->roles[0]->id;
                $data = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();

                if ($data) {
                    $type = $request->type;
                    $value_type = $request->value_type;
                    $value = (float)$request->value;
                    $permittedFlatValue = ($data->$type) / 100 * $rerate_log->annual_ins;
                    $permittedPercentValue = $data->$type;

                    if ($value_type == 1) {
                        $v_perc = ($value / $rerate_log->annual_ins) * 100;
                        $v_flat = $value;
                    } elseif ($value_type == 2) {
                        $v_perc = $value;
                        $v_flat = ($value / 100) * $rerate_log->annual_ins;
                    } else {
                        DB::rollBack();
                        return array('success'=>0,'message'=>'Value type not found');
                        //return Redirect::back()->with('error', 'Value type not found');
                    }

                    if ($type == 'discount') {
                        if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                            return array('success'=>0,'message'=>'Can not exceed maximum discount value allowed');
                            //return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');
                    }

                    if ($type == 'surcharge') {
                        if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                            return array('success'=>0,'message'=>'Can not exceed maximum surcharge value allowed');
                            //return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');
                    }

                    if ($v_perc <= $permittedPercentValue) {
                        if ($type == 'discount') {
                            if (((float)$totalDisc + $v_perc) > $permittedPercentValue)
                                return array('success'=>0,'message'=>'Can not exceed maximum discount value allowed');
                                //return Redirect::back()->with('error', 'Can not exceed maximum discount value allowed');

                            $annual = number_format((float)$rerate_log->annual_ins - $v_flat, 2, '.', '');
                        } elseif ($type == 'surcharge') {
                            if (((float)$totalSurcValue + $v_perc) > $permittedPercentValue)
                                return array('success'=>0,'message'=>'Can not exceed maximum surcharge value allowed');
                                //return Redirect::back()->with('error', 'Can not exceed maximum surcharge value allowed');

                            $annual = number_format((float)$rerate_log->annual_ins + $v_flat, 2, '.', '');
                        } else {
                            return array('success'=>0,'message'=>'Type not found');
                            //return Redirect::back()->with('error', 'Type not found');
                        }

                        $rerate_log->annual_ins = number_format((float)$annual, 2, '.', '');
                        $rerate_log->three_ins = number_format((float)$annual / 3, 2, '.', '');
                        $rerate_log->month_ins = number_format((float)$annual / 12 * 1.08, 2, '.', '');
                        if ($type == 'discount') {
                            $rerate_log->discount = $rerate_log->discount + number_format((float)$v_perc, 2, '.', '');
                        }

                        if ($type == 'surcharge') {
                            $rerate_log->surcharge = $rerate_log->surcharge + number_format((float)$v_perc, 2, '.', '');
                        }

                        $premium_rate =  number_format((float)($rerate_log->annual_ins / $rerate_log->sum_assured) * 100, 2, '.', '');
                        $rerate_log->save();


                        if ($premium_rate < 2.24) { //updated with 2.24
                            DB::rollBack();
                            return array('success'=>0,'message'=>'Premium rate can not go below 2.24%');
                            //return Redirect::back()->with('error', 'Premium rate can not go below 2%');
                        } else {
                            DB::commit();
                            return array(
                                'success'=>1,
                                'message'=>'Discount/Surcharge added successfully',
                                'premium'=>[
                                    'monthly'=>$rerate_log->month_ins,
                                    'three'=>$rerate_log->three_ins,
                                    'yearly'=>$rerate_log->annual_ins
                                ]
                            );
                            //return redirect()->route('quote.rerate_billing', ['id' => $policy->id])->with('success', 'Added ' . $type);
                        }
                    } else {
                        DB::rollBack();
                        return array('success'=>0,'message'=>'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                        //return Redirect::back()->with('error', 'You are only permitted to ' . $type . ' upto ' . ($value_type == 1 ? $permittedFlatValue : $permittedPercentValue . '%' . ' for this quote'));
                    }
                } else {
                    return array('success'=>0,'message'=>'Values not found for role : ' . $Role->roles[0]->name);

                    //return Redirect::back()->with('error', 'Values not found for role : ' . $Role->roles[0]->name);
                }
            } else {
                return array('success'=>0,'message'=>'You have exceeded maximum number of updates allowed');

                //return Redirect::back()->with('error', 'You have exceeded maximum number of updates allowed');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return array('success'=>0,'message'=>$e->getMessage().' '.$e->getLine().' '.$e->getFile());

        }
    }

    public function premiumUpdateHistory($id){
        try{
            return view('admin.policy.policy_rerating_history', compact('id'));
        }catch(\Exception $ex){

        }
    }

    public function getPremiumUpdateHistory($id)
        {
            $data = PolicyPremiumReratingLog::where('policy_number', $id)
                ->orderBy('id','desc')
                ->get();

            return DataTables::of($data)
                ->editColumn('discount', function ($data) {
                    if ($data->discount == null) {
                        $cred = '<span class="kt-font-bold kt-font-primary">-</span>';
                        return $cred;
                    } else {
                        $cred = '<span class="kt-font-bold kt-font-primary">'. $data->discount .'</span>';
                        return $cred;
                    }
                })
                ->editColumn('rerated_by', function ($data) {
                    if ($data->rerated_by) {
                        $user = User::where('id',$data->rerated_by)->first(array('firstName','lastName'));
                        return $user->firstName.' '.$user->lastName;
                    } else {
                        $cred = '-';
                        return $cred;
                    }
                })
                ->editColumn('surcharge', function ($data) {
                    if ($data->surcharge == null) {
                        $cred = '<span class="kt-font-bold kt-font-primary">-</span>';
                        return $cred;
                    } else {
                        $cred = '<span class="kt-font-bold kt-font-primary">'. $data->surcharge .'</span>';
                        return $cred;
                    }
                })
                ->editColumn('status', function ($data) {
//                    if ($data->status == 1) {
//                        $cred = '<span class="kt-font-bold kt-font-success">Accepted</span>';
//                        return $cred;
//                    }elseif($data->status == 2) {
//                        $cred = '<span class="kt-font-bold kt-font-primary">In Progress</span>';
//                        return $cred;
//                    }elseif($data->status == 0) {
//                        $cred = '<span class="kt-font-bold kt-font-danger">Rejected</span>';
//                        return $cred;
//                    } else {
//                        $cred = '<span class="kt-font-bold kt-font-primary">-</span>';
//                        return $cred;
//                    }

                    switch($data->status){
                        case '1':
                            $cred = '<span class="kt-font-bold kt-font-primary">Accepted</span>';
                            break;
                        case '2':
                            $cred = '<span class="kt-font-bold kt-font-primary">In Progress</span>';
                            break;
                        case '0':
                            $cred = '<span class="kt-font-bold kt-font-primary">-</span>';
                            break;
                        default:
                            $cred = '<span class="kt-font-bold kt-font-primary">-</span>';
                            break;
                    }

                    return $cred;
                })
                ->editColumn('payment_status', function ($data) {
                    $policy = Policy::where('policyNumber',$data->policy_number)->first(array('id'));

                    if ($data->status == 0 && $data->payment_status == 0) {
                        $payment_button = '<a href="' . route('admin.policy.rerate_billing', ['id' => $data->id]) . '" target="_blank">Process Payment</a>';
                        return $payment_button;
                    }elseif ($data->status == 1 && $data->payment_status == 1) {
                        $payment_button = '<span class="kt-font-bold kt-font-success">Payment Processed</span>';
                        return $payment_button;
                    } else {
                        $payment_button = '<span class="kt-font-bold kt-font-primary">-</span>';
                        return $payment_button;
                    }
                })
                ->editColumn('rating_detail', function ($data) {
                    if ($data->ratings_id != null) {
                        $details = '<a href="' . route('admin.policy.getRateDetails', ['id' => $data->ratings_id]) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View" target="_blank">
                                <i class="la la-eye"></i>
                            </a>';

                        return $details;
                    } else {
                        $details = '<span class="kt-font-bold kt-font-primary">-</span>';
                        return $details;
                    }
                })
                ->rawColumns(['status','discount','surcharge','payment_status','rating_detail','rerated_by'])
                ->make(true);
        }

    public function rerateBilling($rateLogId=null)
    {
        try{
            if($rateLogId) {
                $reratelog = PolicyPremiumReratingLog::where('id',$rateLogId)->first();
                if($reratelog){
                    $policy = Policy::where('policyNumber',$reratelog->policy_number)->first(array('id','policyNumber'));
                    $bankingDetails = CustomerBanking::where('policy_id',$policy->id)->orderBy('id','desc')->first();
                    $banks = Banks::get(array('id','bank_number','bank_name'));
                    $branches = BankBranches::where('bank_id',$bankingDetails->bankName)->get(array('id','name','bank_id','branch_id'));
                    //return view('admin.policy.policy_rerating_billing', compact('policy','banks','reratelog','bankingDetails'));
                    return view('admin.policy.policy_rerating_billing', compact('policy','banks','branches','bankingDetails','reratelog'));
                }
            }
            else {
                return Redirect::back()->with('error', 'Rate log ID not found');
            }
        }catch(\Exception $ex){
            return Redirect::back()->with('error', $ex->getMessage());
        }
    }

    public function getCashPaymentLoggedData($id)
    {
        $payTrx = PaymentTransaction::where('policyNumber', $id)->where('paymentAlreadyLog',1)->get();
        return DataTables::of($payTrx)

            ->editColumn('contractNumber', function ($payTrx) {
                $contractNumber = 'N/A';
                if (isset($payTrx->paymentMethod) && $payTrx->paymentMethod == 'RealPay') {
                    $realpayInstalment = RealpayContractInstallments::where('InstalmentReferenceNumber',$payTrx->referenceNumber)->first();
                    if (isset($realpayInstalment)) {
                        $contractNumber = $realpayInstalment->contractNumber;
                    } else {
                        $contractNumber = 'N/A';
                    }

                }
                return $contractNumber;
            })

            ->editColumn('paymentFrequency', function ($payTrx) {
                if ($payTrx->paymentFrequency == 1) {
                    return  'Monthly';
                } elseif ($payTrx->paymentFrequency == 2) {
                    return  'Three Instalments';
                } elseif ($payTrx->paymentFrequency == 3) {
                    return  'Annual';
                } else {
                    $policy = Policy::where('policyNumber', $payTrx->policyNumber)->first(array('premium_freq'));
                    if ($policy->premium_freq == 1) {
                        return  'Monthly';
                    } elseif ($policy->premium_freq == 2) {
                        return  'Three Instalments';
                    } elseif ($policy->premium_freq == 3) {
                        return 'Annual';
                    } else {
                        return 'Monthly'; // Monthly on null
                    }
                }
            })

            ->editColumn('reason', function ($payTrx) {
                return $payTrx->reason;
            })

            ->editColumn('refunded_by', function ($payTrx) {
                $return = ucfirst($payTrx->refunded_by);
                return $return;
            })

            ->editColumn('actions', function ($payTrx) {
                if ($payTrx->payment_proof_link) {
                    $action = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($payTrx->payment_proof_link) . '" target= "_blank" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View proof of payment">
                                <i class="la la-eye"></i>
                            </a>';
                    return  $action;
                } else {
                    return '-';
                }
            })
            ->editColumn('paymentMethod', function ($payTrx) {
                if ($payTrx->paymentMethod == 'orangeMoney') {
                    return  'Orange USSD';
                } else {
                    return $payTrx->paymentMethod;
                }
            })
            ->editColumn('paymentLoggedBy', function ($payTrx) {
                if ($payTrx->paymentLoggedBy != null) {
                    $user = User::where('id',$payTrx->paymentLoggedBy)->first();
                    return $user->firstName.' '.$user->lastName;
                } else {
                    return '-';
                }
            })
            ->editColumn('cashRecipient', function ($payTrx) {
                if ($payTrx->cashRecipient != null) {
                    return $payTrx->cashRecipient;
                } else {
                    return '-';
                }
            })
            ->editColumn('created_at', function ($payTrx) {
                if ($payTrx->created_at != null) {
                    return  Carbon::parse($payTrx->created_at)->format('Y-m-d H:i');
                } else {
                    return '-';
                }
            })

            ->rawColumns(
                [
                    'contractNumber',
                    'paymentFrequency',
                    'actions',
                    'paymentMethod',
                    'paymentLoggedBy',
                    'cashRecipient',
                    // 'paymentDate'
                ]
            )
            ->make(true);
    }

    public function getRateDetails($id){
        $data = PolicyPremiumReratingLog::where('ratings_id',$id)->first();
        if($data){
            return view('admin.policy.ratings_details', compact('data'));
        }else{
            return Redirect::back()->with('error', 'No data found');
        }
    }

    public function updatePolicyDates($policyNo, $status)
    {
        try {
            $updateDate = new PolicyStatusLogs();
            $updateDate->policyNumber = $policyNo;
            switch ($status) {
                case '1':
                    $updateDate->activated_date = Carbon::parse('today')->format('Y-m-d');
                    break;

                case '2':
                    $updateDate->cancelled_date = Carbon::parse('today')->format('Y-m-d');
                    break;

                default:
                    Log::error('Unable to activate policy status log date');
                    break;
            }

            $updateDate->save();
            return 1;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return 0;
        }
    }

    public function viewArchivedPolicies()
    {
        try {
            if (auth::user()->hasPermissionTo('policy-list')) {
                $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
                $policies = Policy::all();
                $agents = Policy::join('users', 'users.id', 'policies.agent_id')
                    ->where('users.active', 1)
                    ->where('policies.agent_id', '!=', 'null')
                    ->groupBy('policies.agent_id')
                    ->get();
                // Show the page
                return view('admin.policy.archived', compact('products', 'policies', 'agents'));
            } else {
                return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getPaymentInfo($policyNumber)
    {
        try {
            $transaction = Transaction::leftJoin('payment_transactions', 'payment_transactions.policyNumber', 'transactions.policyNumber')
                ->leftJoin('policies', 'policies.policyNumber', 'payment_transactions.policyNumber')
                ->leftJoin('customer_banking', 'customer_banking.policy_id', 'policies.id')
                ->where('transactions.policyNumber', $policyNumber)
                ->orderBy('transactions.id', 'DESC')
                ->first(array(
                    'transactions.orangeTransaction_id',
                    'transactions.vcsTransaction_id',
                    'transactions.realPayTransaction_id',
                    'transactions.flutterwave_id',
                    'transactions.policyNumber',
                    'transactions.status',
                    'payment_transactions.paymentMethod',
                ));


            dd($transaction);
        } catch (\Exception $ex) {
            dd($ex->getMessage() . ' ' . $ex->getLine());
        }
    }

    public function restorePolicyBankingDetails()
    {
        try {
            $policies = Policy::orderBy('id', 'DESC')->get(array('id', 'policyNumber'));

            foreach ($policies as $key => $policy) {
                if ($policy->id != null) {
                    $transaction = Transaction::leftJoin('payment_transactions', 'payment_transactions.policyNumber', 'transactions.policyNumber')
                        ->where('transactions.policyNumber', $policy->policyNumber)
                        ->orderBy('transactions.id', 'DESC')
                        ->first();

                    $banking = CustomerBanking::where('policy_id', $policy->id)
                        ->orderBy('id', 'DESC')
                        ->first();
                }
            }
        } catch (\Exception $ex) {
        }
    }

    public function getPolicyPaymentDetails($policyNumber)
    {
        try {
            $transaction = Transaction::leftJoin('payment_transactions', 'payment_transactions.referenceNumber', 'transactions.referenceNumber')
                ->where('transactions.policyNumber', $policyNumber)
                ->orderBy('transactions.id', 'DESC')
                ->first(array(
                    'transactions.policyNumber as tr_policyNumber',
                    'transactions.referenceNumber as tr_referenceNumber',
                    'transactions.status as tr_status',
                    'transactions.orangeTransaction_id',
                    'transactions.vcsTransaction_id',
                    'transactions.realPayTransaction_id',
                    'transactions.flutterwave_id',
                    'payment_transactions.policyNumber as pt_policyNumber',
                    'payment_transactions.referenceNumber as pt_referenceNumber',
                    'payment_transactions.status as pt_status',
                    'payment_transactions.paymentMethod'
                ));

            if ($transaction->pt_policyNumber != null && $transaction->paymentMethod) {
                $paymentStatus = ucfirst($transaction->pt_status);
                $paymentMethod = ucfirst($transaction->paymentMethod);
                $policyNumber = $transaction->pt_policyNumber;
                $referenceNumber = $transaction->pt_referenceNumber;
            } elseif ($transaction->tr_policyNumber != null) {
                if ($transaction->orangeTransaction_id != null) {
                    $paymentMethod = 'Orange Money';
                    $paymentStatus = ucfirst($transaction->tr_status);
                } elseif ($transaction->vcsTransaction_id != null) {
                    $paymentMethod = 'VCS';
                    $paymentStatus = ucfirst($transaction->tr_status);
                } elseif ($transaction->flutterwave_id != null) {
                    $paymentMethod = 'Flutter Wave';
                    $paymentStatus = ucfirst($transaction->tr_status);
                } elseif ($transaction->realPayTransaction_id != null) {
                    $paymentMethod = 'RealPay';
                    switch ($transaction->tr_status) {
                        case 'a':
                            $paymentStatus = 'Active';
                            break;
                        case 'I':
                            $paymentStatus = 'Cancelled';
                            break;
                        case 's':
                            $paymentStatus = 'Success';
                            break;
                        case 'A':
                            $paymentStatus = 'Active';
                            break;
                        default:
                            $paymentStatus = ucfirst($transaction->tr_status);
                            break;
                    }
                } else {
                    $banking = CustomerBanking::join('policies', 'policies.id', 'customer_banking.policy_id')
                        ->where('policies.policyNumber', $policyNumber)
                        ->orderBy('customer_banking.id', 'DESC')
                        ->first(array('customer_banking.billing'));

                    $paymentMethod = $banking->billing != null ? $banking->billing : 'N/A';
                    $paymentStatus = ucfirst('Payment not initiated');
                }

                $policyNumber = $transaction->tr_policyNumber;
                $referenceNumber = $transaction->tr_referenceNumber;
            } else {
                return array(
                    'status' => false,
                    'message' => 'Data not found',
                );
            }

            return array(
                'status' => true,
                'paymentStatus' => $paymentStatus,
                'paymentMethod' => $paymentMethod,
                'policyNumber' => $policyNumber,
                'referenceNumber' => $referenceNumber,
            );
        } catch (\Exception $ex) {
            return array('status' => false, 'paymentStatus' => $ex->getMessage() . ' Line #' . $ex->getLine());
        }
    }

    public function calculatePerDayPremiumRatingID(Request $request){
        $id = $request->rate_id;
        $diff = $request->diff_days;

        $rate = PolicyPremiumReratingLog::where('ratings_id',$id)->first();

        $perday = number_format((float)$rate->month_ins/30.4375, 2, '.', '');
        $overall = number_format((float)$perday*$diff, 2, '.', '');

        return response()->json(['status' => 'success', 'perDay' => $perday, 'overall' => $overall]);
    }

    public function updateVehicleInfomation($vehicle){

        $vehicleData = Vehicle::where('policy_id',$vehicle['policy_id'])->first();

        foreach($vehicle as $key=>$data){
            $vehicleData->$key = $data;
        }
        $vehicleData->save();
    }

    public function scheduleTransactionsData($id)
    {

        $data = ScheduleTransaction::where('policy_number', $id)->orderBy('billing_date', 'asc')->orderBy('status', 'asc')->get();

            return Datatables::of($data)

                ->editColumn('status', function ($data) {
                    if ($data->status == 0 ) {
                        $return = '<span class="kt-font-bold text-info">Payment not initiated</span>';
                    }elseif ($data->status == 1) {
                        $return = '<span class="kt-font-bold text-primary">Payment initiated</span>';
                    }elseif ($data->status == 2 ) {
                        $return = '<span class="kt-font-bold text-success">Payment Success</span>';
                    }elseif ($data->status == 3 ) {
                        $return = '<span class="kt-font-bold text-warning">Payment Failed</span>';
                    }elseif ($data->status == 4 ) {
                        $return = '<span class="kt-font-bold text-danger">Canceled</span>';
                    }else {
                        $return = '<span class="kt-font-bold text-muted">-</span>';
                    }

                    return $return;
                })

                ->addColumn('action', function ($data) {
                    $ret = '';

                    if ($data->payment_method == 'orange') {
                        if ($data->status == 0 || $data->status == 3  ) {
                            // if (auth::user()->hasPermissionTo('policy-make_payment_dpo') )
                            // {
                                $ret = '<button class="btn btn-success btn-xs makeOrangePaymentNow m-2"  data-id="'.$data->id.'">Pay</button>';
                            // }

                            // if(auth::user()->hasPermissionTo('policy-suspend_payment_dpo'))
                            // {
                                $ret .= '<button class="btn btn-danger btn-xs removeOrangePayment m-2"  data-id="'.$data->id.'">Cancel</button>';
                            // }
                        }
                        else {
                            $ret = '<span class="kt-font-bold text-muted">-</span>';
                        }
                    } else {
                        if ($data->status == 0 || $data->status == 3  ) {
                            if (auth::user()->hasPermissionTo('policy-make_payment_dpo') )
                            {
                                $ret = '<button class="btn btn-success btn-xs makePaymentNow m-2"  data-id="'.$data->id.'">Pay</button>';
                            }

                            if(auth::user()->hasPermissionTo('policy-suspend_payment_dpo'))
                            {
                                $ret .= '<button class="btn btn-danger btn-xs suspendPaymentDpo m-2"  data-id="'.$data->id.'">Cancel</button>';
                            }
                        }
                        else {
                            $ret = '<span class="kt-font-bold text-muted">-</span>';
                        }
                    }

                    return $ret;
                })

                ->editColumn('billing_date', function ($data) {
                    $return = Carbon::parse($data->billing_date)->format('d/m/Y H:i:s');
                    return $return;
                })

                ->editColumn('created_at', function ($data) {
                    $return = Carbon::parse($data->created_at)->format('d/m/Y H:i:s');
                    return $return;
                })

                ->editColumn('reason', function ($data) {
                    return $data->reason;
                })
                ->editColumn('added_by', function ($data) {

                    if(isset($data->added_by)){
                              $user =  User::where('id', $data->added_by)->first();
                              if($user){
                              $added_by =   $user->firstName.' '. $user->lastName;
                              }else{
                              $added_by = "System";
                                  }
                      }else{
                         $added_by = "System";
                      }
                      return $added_by;
                  })

                ->addIndexColumn()
                ->rawColumns(['status','billing_date', 'created_at','added_by','reason','action'])
                ->make(true);
    }

    public function setPoliyFrequency(Request $request){
        try{
            $policyNumber = $request->policyNumber;
            $newFreq = $request->newFrequency;

            if($policyNumber && $newFreq){
                $policy = Policy::where('policyNumber',$policyNumber)->first(array('id','policyNumber','premium_freq','premium'));
                if($policy && $policy->premium_freq){
                    $frequencyChange = $policy->premium_freq.''.$newFreq;
                    switch ($frequencyChange){
                        case 11 :
                            $newPremium = $policy->premium;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 12 :
                            $newPremium = ($policy->premium*3)/1.12;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 13 :
                            $newPremium = ($policy->premium*12)/1.12;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 21 :
                            $newPremium = ($policy->premium/3)*1.12;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 22 :
                            $newPremium = $policy->premium;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 23 :
                            $newPremium = ($policy->premium*4);
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 31 :
                            $newPremium = ($policy->premium/12)*1.12;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 32 :
                            $newPremium = ($policy->premium/12)*3;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                        case 33 :
                            $newPremium = $policy->premium;
                            return response()->json(['status' => 1, 'premium' => $newPremium],200);
                    }
                }else{
                    return response()->json(['status' => 0],401);
                }
            }else{
                return response()->json(['status' => 0],401);
            }
        }catch(\Exception $ex){
            return response()->json(['status' => 0,'message'=>$ex->getMessage().' '.$ex->getLine()],401);
        }
    }

    public function cancelPolicyFromAll(CancelPolicyRequest $request)
    {

        try{
            \Illuminate\Support\Facades\DB::beginTransaction();
            if(Policy::where('id', $request->policy_id)->exists())
            {
                $policy = Policy::where('id', $request->policy_id)->first();

                if($policy->status == 2)
                {
                    return response()->json(['success' => 0, 'message' => 'policy is canceled already.'], 401);
                }

                $feedback               = new CustomerFeedback();
                $feedback->policy_id    = $policy->id;
                $feedback->cancelled_by = null;
                $feedback->customer_id  = $policy->customer_id;
                $feedback->product_id   = $policy->product_id;

                if ($request->reason) {
                    $feedback->reason = $request->reason;
                }
                if ($request->circumstances != null) {
                    $feedback->circumstances = $request->circumstances;
                }
                if ($request->other_company != null) {
                    $feedback->other_company = $request->other_company;
                }
                $feedback->save();

                //change status of policy
                $policy->status = 2;
                $policy->save();

                $banking = CustomerBanking::where('policy_id', $policy->id)->first(); //open this when the customer_banking table bug fixes

                    $transctionsSelect = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();
                    if (!isset($transctionsSelect)) {
                        $transctionsSelect = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('paymentMethod', 'DPO')->orderBy('id', 'desc')->first();
                        if(!isset($transctionsSelect))
                        {
                            return response()->json(['success' => 0, 'message' => 'Banking details not found.'], 401);
                        }
                    }

                        if(isset($transctionsSelect->paymentMethod) && $transctionsSelect->paymentMethod == 'DPO')
                        {
                            $banking->billing = 'DPO';
                        }

                        if ($transctionsSelect->referenceNumber && $transctionsSelect->referenceNumber != '') {
                            $banking->billing = 'VCS';
                        }

                        if ($transctionsSelect->realPayTransaction_id && $transctionsSelect->realPayTransaction_id != '') {
                            $banking->billing = 'RealPay';
                        }

                        if ($banking->billing == 'VCS') {
                            $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();
                            if ($transctionsRow && $transctionsRow->referenceNumber) {
                                $referenceNumber = $transctionsRow->referenceNumber;
                                $vcs = new PaymentController;
                                $vcs->suspendTransactionOnVCS($referenceNumber);
                                $policy->status = 2;
                                $policy->save();
                            }
                        } elseif ($banking->billing == 'RealPay') {
                            $check = RealpayPaymentRequest::where('policy_id', $request->policy_id)->first();
                            if ($check != null && $check->status == 1 && ($check->contract == $request->policy_id)) {
                                $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                $addLog = $log->logEvent($policy->id, 2);

                                if ($addLog) {
                                    $request                           = new RealpayCancelRequests();
                                    $request->policy_id                = $policy->id;
                                    $request->leftout_premium_contract = null;
                                    $request->contract                 = $banking->contract_number;
                                    $request->cancel_status            = 0;
                                    $request->save();

                                } /* else {
                                    return response()->json(['success' => 0], 401);
                                } */
                            }
                        }
                        elseif($banking->billing == 'DPO') {
                            $data = [
                                "token"            => ScheduleTransaction::where('policy_number', $policy->policyNumber)->where('status', 1)->value('token'),
                                "policy_number"    => $policy->policyNumber,
                                "CompanyRef"       => env('COMPANY_REF'),
                                "customer_id"      => $policy->customer_id,
                            ];

                            if($data['token'] != null )
                            {
                                CancelTokenEvent::dispatch($data);
                            }

                            if(ScheduleTransaction::where('policy_number', $data['policy_number'])->exists())
                            {
                                CancelScheduleTransactionEvent::dispatch($data);
                            }

                        }


                    $customer = Customer::where('id', $request->customer_id)->first();
                    $data = new \stdClass();
                    $data->policy_id   = $request->policy_id;
                    $data->customer_id = $customer->id;
                    $data->hook        = 'cancel_policy';
                    $data->attachment  = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));

                   // Mail::to($customer->email)->send(new MailTemplate($data));

                    //sms
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailPolicyCancelled(24, $policy->policyNumber, $customer->firstName, $customer->cellphone);

                    $pc     = new PolicyController();
                    $update = $pc->updatePolicyDates($policy->policyNumber, 2);
                    \Illuminate\Support\Facades\DB::commit();
                    return response()->json(['success' => 1], 200);

            }
            else{
                return response()->json(['success' => 0, 'message' => 'Policy not found'], 401);
            }

        }
        catch (\Illuminate\Validation\ValidationException $ex) {
            \Illuminate\Support\Facades\DB::rollback();
            return response()->json(['success' => 0, 'message' => $ex], 401);
        }
        catch (\Exception $ex) {
            \Illuminate\Support\Facades\DB::rollback();
            return response()->json(['success' => 0, 'message' => $ex], 401);
        }
    }

    public function processRenewalPolicy(Request $request)
    {
        try {
            // dd($request->all());
            // switch ($request->input('action')) {
            //     case 'cash':
            //         return redirect()->route('admin.policy.addOfflinePayment', $request->all());
            //         break;

            //     case 'realpay':
            //         return redirect()->route('admin.policy.addOfflinePayment', $request->all());
            //         break;
            // }

            return redirect()->route('admin.policy.addOfflinePayment', $request->all());

            // return redirect()->route('admin.policy.addOfflinePayment')->withInput($request->all());

        } catch (\Exception $ex) {
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$ex->getMessage());
        }
    }

    public function addOfflinePaymentPolicy(Request $request)
    {
        // dd($request->all());
        if ($request->input('action') == 'cash') {

            $policy = Policy::where('id', $request->policy_id)->first();
            $banks  = Banks::all();
            $user = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
            $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
            if($banking != null && $banking->billing == 'RealPay')
            {
                $banking->bank_name   = Banks::where('bank_number', $banking->bankName)->value('bank_name');
                $banking->bank_branch = BankBranches::where('branch_id', $banking->branchCode)->value('name');
            }

            $new_premium = $request->new_premium;
            $term_start_date = $request->term_start_date;
            $agent_id = $request->agent_id;

            if(isset($request->reinstate_type))
            {
               $reinstate_type = $request->reinstate_type;
            }else{
                $reinstate_type = Null;
            }

            if(isset($request->name))
            {
               $name=$request->name;
            }else{
                $name=Null;
            }
            return view('admin.policy.addOfflinePayment',compact('policy','banks','user','banking','new_premium','term_start_date','agent_id','name','reinstate_type'));

        } elseif ($request->input('action') == 'realpay') {
            $policy = Policy::where('id', $request->policy_id)->first();
            $banks  = Banks::all();
            $user = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
            $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
            if($banking != null && $banking->billing == 'RealPay')
            {
                $banking->bank_name   = Banks::where('bank_number', $banking->bankName)->value('bank_name');
                $banking->bank_branch = BankBranches::where('branch_id', $banking->branchCode)->value('name');
            }

            $new_premium = $request->new_premium;
            $term_start_date = $request->term_start_date;
            $agent_id = $request->agent_id;

            if(isset($request->reinstate_type))
            {
               $reinstate_type = $request->reinstate_type;
            }else{
                $reinstate_type = Null;
            }

            if(isset($request->name))
            {
               $name=$request->name;
            }else{
                $name=Null;
            }

            return view('admin.policy.addRealPayPayment',compact('policy','banks','user','banking','new_premium','term_start_date','agent_id','name','reinstate_type'));
        }
    }


    public function storeCashPaymentForRenewal(Request $request)
    {

        // if (auth::user()->hasPermissionTo('offline-payments-list') || ('offline-payments-edit')) {
                //dd($request);
            try {
                // dd($request->all());
                $policy = Policy::where('policyNumber', $request->policyNumber)->first(array('id', 'policyNumber', 'customer_id', 'status', 'quoteNumber'));

                if ($policy->status != 1) {
                    $update = $this->updatePolicyDates($policy->policyNumber, 1);
                }
                //$customer = Customer::where('id',$policy->customer_id)->first(array('cellphone'));
                $entry = new PaymentTransaction();
                $entry->policyNumber = $request->policyNumber;
                $entry->referenceNumber = Carbon::now()->timestamp.'/'.$request->receiptNumber;
                $entry->amount = $request->paymentAmount;
                $entry->status = 'SUCCESS';
                $entry->paymentDate = $request->paymentDate;
                $entry->paymentMethod = 'CASH';
                $entry->is_ledger = 0;
                $entry->cashRecipient = $request->paymentRecievedBy;
               // $entry->paymentFrequency = $request->paymentFreq;
                $entry->numberOfInstalmentsPaid = $request->numberOfInstalmentsPaid;
                $entry->note = $request->paymentNote;
                $entry->paymentLoggedBy = auth()->user()->id;
                if ($request->hasFile('payment_image')) {
                    $file = $request->file('payment_image');
                    $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $filePath = 'PolicyPayment/' . $request->policyNumber . '-' . $entry->referenceNumber;
                    Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $entry->payment_proof_link = $filePath;
                }
                $entry->save();
                if ($entry->save()) {
                    $check = CustomerBanking::where('policy_id', $policy->id)->first();
                    if ($check == null) {
                        $banking = new CustomerBanking();
                    } else {
                        $banking = CustomerBanking::where('policy_id', $policy->id)->first();
                    }
                    $banking->customer_id = $policy->customer_id;
                    $banking->policy_id = $policy->id;
                    if (isset($request->addRealpay) && $request->addRealpay == 1)
                        $banking->billing = "RealPay";
                    $banking->billingCell = $request->billingCell;
                    $banking->bankName = $request->RPBanks;
                    $banking->branchCode = $request->RPBankBranch;
                    $banking->accountType = $request->accountType;
                    $banking->billingStartDate = $request->paymentStartDate;
                    $banking->accountNumber = $request->accountNumber;
                    $banking->billing_day = date("d", strtotime($request->paymentStartDate));
                    $banking->save();

                    if ($policy->product_id == 3) {
                        if ($policy->quoteNumber != null) {
                            $compQuote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();

                            if ($request->paymentFreq != null && $compQuote != null) {
                                if ($request->paymentFreq == 1) {
                                    $premium = $compQuote->premiumMonthly;
                                } elseif ($request->paymentFreq == 2) {
                                    $premium = $compQuote->premium3Inst;
                                } elseif ($request->paymentFreq == 3) {
                                    $premium = $compQuote->premiumAnnually;
                                } else {
                                    return response()->json(['status' => '401', 'message' => 'Premium not found in Quote as per selected frequency']);
                                }
                                // $policy->billingStartDate = $request->paymentStartDate;
                                // $policy->billing_day = $banking->billing_day;
                                // $policy->premium = $premium;
                                // $policy->status = 1;
                                if (isset($request->name) && $request->name == "Reinstate")
                                {
                                    $policy->trans_type='REINSTATE';
                                }else{
                                    $policy->trans_type='RENEW';
                                }
                                // $policy->premium_freq = $request->paymentFreq;
                                // $policy->policyActivatedDate =  Carbon::now()->format("Y-m-d");
                                // $saved = $policy->save();

                                // $policy->billingStartDate = $request->paymentStartDate;
                                // $policy->billing_day = $banking->billing_day;
                                // $policy->premium = $premium;
                                // $policy->status = 1;
                                if (isset($request->name) && $request->name == "Reinstate")
                                {
                                    $policy->trans_type='REINSTATE';

                                }else{
                                    $policy->trans_type='RENEW';
                                }
                                // $policy->premium_freq = $request->paymentFreq;
                                // $policy->policyActivatedDate =  Carbon::now()->format("Y-m-d");
                                // $policy->save();
                            } else {
                                return response()->json(['status' => '401', 'message' => 'Payment frequency found null.']);
                            }
                        } else {
                            return response()->json(['status' => '401', 'message' => 'Quote number not found for this policy']);
                        }
                    }

                    if (isset($request->addRealpay) && $request->addRealpay == 1 && $request->accountNumber  && $request->RPBanks  && $request->RPBankBranch  && $request->accountType) {

                        $request['policyID'] = $policy->id;
                        $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                        $cancelContract = $realpayCon->CancelRealpayPaymentContract($request);
                        if ($cancelContract->getData()->status == 200) {
                            $policy = Policy::where('id',$request->policyID)->orderBy('id','DESC')->first();
                        $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
                        $profile = CustomerProfile::where('customer_id',$customer->id)->first();
                        $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();

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

                        $fetchToken = $realpayCon->clientAuth();
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

                        $checkClient = $realpayCon->checkClientExists($policy->id);

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
                                    \"BankCode\": \"$banking->bankName\",\r\n
                                    \"BranchCode\": \"$banking->branchCode\",\r\n
                                    \"AccountType\": \"$banking->accountType\",\r\n
                                    \"AccountNumber\": \"$banking->accountNumber\",\r\n
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
                                    \"BankCode\": \"$banking->bankName\",\r\n
                                    \"BranchCode\": \"$banking->branchCode\",\r\n
                                    \"AccountType\": \"$banking->accountType\",\r\n
                                    \"AccountNumber\": \"$banking->accountNumber\",\r\n
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


                        //if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
                            $customerBanking->bankName = $request->BankName;
                            $customerBanking->branchCode = $request->BranchCode;
                            $customerBanking->accountType = $request->accountType;
                            $customerBanking->accountNumber = $request->accountNumber;
                            $customerBanking->billing = "RealPay";
                            $customerBanking->billing_day = $request->billing_day;
                            $customerBanking->billingStartDate = $realpayCon->setDate($request->billing_day);
                            $customerBanking->save();

                            $fetchToken = $realpayCon->clientAuth();
                            if($fetchToken['token_type'] && $fetchToken['access_token'])
                                $token = $fetchToken['token_type'].' '.$fetchToken['access_token'];
                            else
                                return null;

                                $policy = Policy::where('id',$policy->id)->first();
                            // dd($policy,$token,$policy->id);
                            if($policy->product_id != 3)
                                $policy->first_premium_wvat = 0;

                            $firstBillingDate = $request->first_collection_date;
                            $policyCon = new PolicyController();
                            $premium = $policyCon->getMonthlyPrem(3,$request->premium);
                            $premium = round($premium,2);
                            if ($premium != $request->paymentAmount) {
                                $remainingAmt = $premium - $request->paymentAmount;
                                $amount = $remainingAmt + $premium;
                                $firstCollectionAmount = round($amount,2);
                            }else {
                                $firstCollectionAmount = $premium;
                            }
                            // $firstCollectionAmount = $request->first_premium;
                            $numberOfInstallments = 12 - $request->numberOfInstallmentsPaid;
                            // $numberOfInstallments = '12';
                            $paymentFreq = 'MNTH';
                            $premium = $request->premium;

                            if($request->paymentFreq != null){

                                if($request->paymentFreq == 2){
                                    if ($request->numberOfInstallmentsPaid < 3) {
                                        $numberOfInstallments = 3 - $request->numberOfInstallmentsPaid;
                                        $premium = $request->premium / 3;
                                        $premium = round($premium,2);

                                        $firstCollectionAmount = $premium;
                                        // $numberOfInstallments = '3';
                                        // $totalInstlAmt = $request->paymentAmount + $request->first_premium;
                                        // $premium = $request->premium - $totalInstlAmt;
                                        // $premium = ($request->premium - $request->first_premium) / 2;
                                        // $firstCollectionAmount = $premium;
                                    } else {
                                        return response()->json(['status' => '401', 'message' => 'Number of installment paid should not be less than 3']);
                                    }
                                }
                                elseif($request->paymentFreq == 3){
                                    if ($request->numberOfInstallmentsPaid < 1) {
                                        $numberOfInstallments = 1 - $request->numberOfInstallmentsPaid;
                                        $paymentFreq = 'YEAR';
                                        // $numberOfInstallments = '1';
                                        $premium = $request->premium;
                                        $firstCollectionAmount = $premium;
                                    } else {
                                        return response()->json(['status' => '401', 'message' => 'Number of installment paid should not be less than 1']);
                                    }
                                }
                                elseif($request->paymentFreq == 1){
                                    if ($request->numberOfInstallmentsPaid < 12) {
                                        $numberOfInstallments = 12 - $request->numberOfInstallmentsPaid;
                                        $policyCon = new PolicyController();
                                        $premium = $policyCon->getMonthlyPrem(3,$request->premium);
                                        $premium = round($premium,2);
                                        if ($premium != $request->paymentAmount) {
                                            $remainingAmt = $premium - $request->paymentAmount;
                                            $amount = $remainingAmt + $premium;
                                            $firstCollectionAmount = round($amount,2);
                                        }else {
                                            $firstCollectionAmount = $premium;
                                        }
                                        // $numberOfInstallments = '12';
                                        // $totalInstlAmt = $request->paymentAmount + $request->first_premium;
                                        // $premium = ($request->premium - $totalInstlAmt) / ($numberOfInstallments - 1);
                                        // $premium = ($request->premium - $totalInstlAmt) / 11;
                                        // $firstCollectionAmount = $premium;
                                    } else {
                                        return response()->json(['status' => '401', 'message' => 'Number of installment paid should not be less than 12']);
                                    }
                                }
                                else{

                                    if($policy->quoteNumber) {
                                        $quote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first(array('premiumMonthly'));
                                        $premium = $quote->premiumMonthly;
                                        $firstCollectionAmount = $premium;
                                        $numberOfInstallments = 12 - $request->numberOfInstallmentsPaid;
                                        // $numberOfInstallments = '12';
                                        $policy->premium_freq = 1;
                                        $policy->save();
                                    }else{
                                        return null;
                                    }
                                }
                            }

                            // dd($premium);
                            // dd($request->all(),$request->paymentFreq,$numberOfInstallments);
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
                                  \"FrequencyCode\": \"$paymentFreq\",\r\n
                                  \"CollectionDay\": \"$billing_day\",\r\n
                                  \"TrackingCode\": \"44\",\r\n
                                  \"FirstCollectionDate\": \"$request->billingDay\",\r\n
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
                            // dd($data);
                                if (isset($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments'])) {
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
                                    $contract = $realpayCon->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                                    $installments = $realpayCon->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
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

                                return response()->json(['status' => '200', 'message' => 'Client added successfully on realpay', "instalmentStatus" => $installmentStatus,'premium' => $premium, 'first_premium' => $firstCollectionAmount, 'calculated_premium' => $premium], 200);

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
                        } else {
                            return response()->json(['status' => '401', 'message' => $cancelContract->getData()->message], 401);

                        }
                    } else {
                        return response()->json(['status' => '200', 'message' => 'Entry added without realpay payment','premium' => $request->paymentAmount, 'first_premium' => $request->paymentAmount]);
                    }
                } else {
                    return response()->json(['status' => '401', 'message' => 'Problem storing data in payment transaction table']);
                }
            } catch (Exception $e) {
                return response()->json(['status' => '401', 'message' => $e->getMessage().' '.$e->getLine()]);
            }
        // } else {
        //     return response()->json(['status' => '401', 'message' => 'Sorry! You do not have permission to access this page!']);
        // }
    }


    public function storeRealPayPaymentForRenewal(Request $request)
    {
        // if (auth::user()->hasPermissionTo('offline-payments-list') || ('offline-payments-edit')) {
            try {
                $policy = Policy::where('policyNumber', $request->policyNumber)->first(array('id', 'policyNumber', 'customer_id', 'status', 'quoteNumber','premium'));
                if ($policy->status != 1) {
                    $update = $this->updatePolicyDates($policy->policyNumber, 1);
                }

                if ($request->accountNumber  && $request->BankName  && $request->BranchCode  && $request->accountType) {
                    $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $renewal = PolicyRenewal::where('policyNumber',$policy->policyNumber)->orderBy('id', 'desc')->first();
                    $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
                    if (isset($data)) {
                        $request['premium'] = $data->new_value;
                    } elseif (isset($renewal) && isset($renewal->new_premium)) {
                        $request['premium'] = $renewal->new_premium;
                    } else {
                        $request['premium'] = $policy->premium;
                    }


                    if (isset($request->name) && $request->name == "Reinstate")
                    {
                        $policy->trans_type='REINSTATE';
                    }else{
                        $policy->trans_type='RENEW';
                    }


                    $realpayPayment = $realpay->logRealpayPaymentForPolicyRenewal($request);
                    if ($realpayPayment->getData()->status == 200) {
                        return response()->json(['status' => '200', 'message' => 'Entry added sucessfully', 'instalmentStatus' => $realpayPayment->getData()->instalmentStatus,'premium' => $realpayPayment->getData()->premium,'first_premium' => $realpayPayment->getData()->first_premium]);
                    } else {
                        return response()->json(['status' => '401', 'message' => 'Failed to add realpay payment'.$realpayPayment->getData()->message]);
                    }
                } else {
                    return response()->json(['status' => '401', 'message' => 'Something went wrong']);
                }
            } catch (Exception $e) {
                //            \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['status' => '401', 'message' => $e->getMessage().' '.$e->getLine()]);

                // return redirect()->back()->with('error', $e->getMessage());
            }
        // } else {
        //     return response()->json(['status' => '401', 'message' => 'Sorry! You do not have permission to access this page!']);

        //     // return redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
        // }
    }


    public function addOfflinePaymentPolicyRenewal(Request $request)
    {
        try {
            $paymentEntry = null;
            if($request->paymentMethod == 'Cash'){
                $paymentEntry = $this->storeCashPaymentForRenewal($request);

            } elseif ($request->paymentMethod == 'Realpay') {
                $paymentEntry = $this->storeRealPayPaymentForRenewal($request);
                $request['policy_id'] = $request->policyID;
                if (isset($request['instalment_status'])) {
                    $request['instalment_status'] = $paymentEntry->getData()->instalmentStatus;
                } else {
                    $request['instalment_status'] = NULL;
                }
            }

            if ($paymentEntry->getData()->status == 200) {
                // dd($paymentEntry);
                if (!isset($request->new_premium)) {
                    $request['new_premium'] = $paymentEntry->getData()->premium;
                }

                if (!isset($request->first_premium)) {
                    $request['first_premium'] = $paymentEntry->getData()->first_premium;
                }

                if (isset($paymentEntry->getData()->first_premium)) {
                    $request['term_permium'] = $paymentEntry->getData()->first_premium;
                }

                if (isset($paymentEntry->getData()->calculated_premium)) {
                    $request['calculated_premium'] = $paymentEntry->getData()->calculated_premium;
                }

                if (isset($request->name) && $request->name == "Reinstate") {
                   //$this->PolicyReinstate($request);
                   if (isset($request->reinstate_type) && $request->reinstate_type == 'Reinstate Accidentally') {
                        if (isset($request->policy_id)) {
                            $policy = Policy::where('id',$request->policy_id)->first();
                            $policyActivatedDate = Carbon::parse($policy->policyActivatedDate)->format('Y-m-d');
                            $activatedCancelledDates = PolicyActivateCancelledDate::where('policyNumber',$policy->policyNumber)->whereNotNull('cancelled_date')->orderBy('id','desc')->first();
                            $data = [
                                'policy_id' => $request->policy_id,
                                'reinstated_by' => auth()->user()->id,
                                'reinstated_date' => Carbon::now()->format('Y-m-d'),
                                'policyActivatedDate' => ($activatedCancelledDates->activated_date) ? $activatedCancelledDates->activated_date : $policyActivatedDate,
                                'policyCancelledDate' => $activatedCancelledDates->cancelled_date,
                                'reinstate_type' => $request->reinstate_type,
                                'is_reinstate' => 1,
                            ];
                            $reinstated = PolicyReinstate::addPolicyReinstated($data);

                            $policy->is_reinstate = 1;
                            $policy->save();
                        }
                        event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                        // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                        return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy reinstate succesfully');

                   } else {
                        $reinstatePolicy = $this->PolicyReinstate($request);
                        // dd($renewpolicy);
                        if ($reinstatePolicy->getData()->status == 200) {
                            event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                            // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy reinstate succesfully');

                        } else {
                            // return Redirect::back()->with('success', 'Failed to renew policy ' . $renewpolicy->getData()->message);
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to reinstate policy ' . $reinstatePolicy->getData()->message);

                        }
                   }


                    // $oldVehicleData = $this->uploadVehicleImages($request , $request->policy_id);
                    // if ($oldVehicleData) {
                    //     $vehicle = Vehicle::where('policy_id',$request->policy_id)->first();
                    //     $vehicle->front = NULL;
                    //     $vehicle->back = NULL;
                    //     $vehicle->left = NULL;
                    //     $vehicle->right = NULL;
                    //     $vehicle->vehicleRegistration = NULL;
                    //     $vehicle->vehicle_valuation = NULL;
                    //     $vehicle->save();
                    // }
                    //return Redirect::back()->with('success', 'Policy reinstate succesfully.');
                    //  event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                    //  dd("end");
                      //return redirect('admin/policy/'.$request->policy_id.'/edit')->with('success', 'Policy reinstate succesfully.');
                    //   return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy reinstate succesfully');
                } else {
                    $renewpolicy = $this->PolicyRenewal($request);
                    // dd($renewpolicy);
                    if ($renewpolicy->getData()->status == 200) {
                        event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Renewed"));
                        // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                        return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy renewed succesfully');

                    } else {
                        // return Redirect::back()->with('success', 'Failed to renew policy ' . $renewpolicy->getData()->message);
                        return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to renew policy ' . $renewpolicy->getData()->message);

                    }
                }
            } else {
                // return Redirect::back()->with('error', 'Failed to renew policy ' . $paymentEntry->getData()->message);
                return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed' . $paymentEntry->getData()->message);

            }

        } catch (\Exception $ex) {
            return \Illuminate\Support\Facades\Redirect::back()->with('error',$ex->getMessage().' '.$ex->getLine());
        }
    }


    public function PolicyRenewal($request)
    {
        try {
            $policyDetails = Policy::where('id', $request->policy_id)->first();

            if ($policyDetails == null) {
                return response()->json(['status' => '401','message' => 'Policy not available for edit.'], 401);
            }

            $policyRenewal = PolicyRenewal::where('policyNumber',$policyDetails->policyNumber)->orderBy('id', 'desc')->first();

            $start_date  = Carbon::createFromFormat('Y-m-d', $request->term_start_date)->format('Y-m-d');
            $expiry_date = Carbon::createFromFormat('Y-m-d', $request->term_start_date)->addYear()->subDays(1)->format('Y-m-d');

            $tranaction = Transaction::where('policyNumber',$policyDetails->policyNumber)->orderBy('id','desc')->first();
            $status = 'Deactive';

            if (isset($policyRenewal->expiry_date)) {
                if ($request->paymentMethod == 'Cash') {
                    if ($policyRenewal->expiry_date < Carbon::now()) {
                        $status = 'Deactive';
                    } else {
                        $status = 'Active';
                    }
                } elseif ($request->paymentMethod == 'Realpay') {
                    if ($policyRenewal->expiry_date < Carbon::now()) {
                        $status = 'Deactive';
                    } else {
                        $status = 'Active';
                    }
                }
            }

            $billingDate = NULL;
            $frequency = NULL;
            if ($request->paymentMethod == 'Cash') {
                $billingDate = $request->paymentDate;
                $frequency = $request->paymentFreq;
            } elseif ($request->paymentMethod == 'Realpay') {
                $billingDate = $request->billingDay;
                $frequency = $request->frequency;
            }

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $oldTermPaymentMethod = $realpay->checkPaymentMethod($request->policy_id);

            $product = Product::where('id', $policyDetails->product_id)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
            $regionVat = Region::where('id', $product->region_id)->first('vat');
            $premium_value = ($request->term_permium) ? $request->term_permium : $request->first_premium;
            $premium_without_vat = $premium_value / (1 + ($regionVat->vat / 100));
            $vat = number_format($premium_value - $premium_without_vat, 2);

            //policy term
            // policy old term data
            $term_count = PolicyTerm::where('policy_id',$request->policy_id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->count();

            if ($term_count == 0) {
                $data = [
                    'policy_id' => $request->policy_id,
                    'term_start_date' => ($policyDetails->policyActivatedDate) ? $policyDetails->policyActivatedDate : $policyDetails->created_at,
                    'term_end_date' => $policyRenewal->expiry_date,
                    // 'premium' => $policyDetails->premium,
                    'premium' => $policyDetails->premium,
                    'annual_premium' => $policyRenewal->old_premium,
                    'vat' => $policyDetails->vat,
                    'vat_percent' => $policyDetails->vat_percent,
                    'renewed_by' => $request->agent_id,
                    'renewals_date' => $policyRenewal->expiry_date,
                    'frequency' => $policyDetails->premium_freq,
                    'first_premium' => $policyDetails->first_premium,
                    'billing_start_date' => $policyDetails->billingStartDate,
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $policyDetails->policyActivatedDate,
                    'payment_method' => $oldTermPaymentMethod,
                    'payment_reference' => $request->policy_id,
                    'trans_type' => 'NEW BUSINESS',
                    'status' => $status,
                    'created_at' => Carbon::now()->format('Y-m-d'),

                ];
                $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
            }

        // policy new term data

        $new_status = 'Deactive';

        if ($request->paymentMethod == 'Cash') {
            if ($status == 'Deactive') {
                if ($expiry_date > Carbon::now()) {
                    $new_status = 'Deactive';
                } else {
                    $new_status = 'Active';
                }
            }
        } elseif ($request->paymentMethod == 'Realpay') {
            if ($status == 'Deactive') {
                if ($expiry_date > Carbon::now()) {
                    $new_status = 'Deactive';
                } else {
                    $new_status = 'Active';
                }
            }
        }

        $term_data = PolicyTerm::where('policy_id',$request->policy_id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->orderBy('id','desc')->first('id');

        $newData = [
            'policy_id' => $request->policy_id,
            'term_start_date' => $start_date,
            'term_end_date' => $expiry_date,
            'premium' => ($request->term_permium) ? $request->term_permium : $request->first_premium,
            'annual_premium' => $request->new_premium,
            'vat' => $vat,
            'vat_percent' => $regionVat->vat,
            'renewed_by' => $request->agent_id,
            'renewals_date' => Carbon::parse($expiry_date)->addDays(1)->format('Y-m-d'),
            'frequency' => $frequency,
            'first_premium' => ($request->term_permium) ? $request->term_permium : $request->first_premium,
            'billing_start_date' => $billingDate,
            'policy_documents' => NULL,
            'policyActivatedDate' => $start_date,
            'payment_method' => $request->paymentMethod,
            'payment_reference' => $request->policy_id,
            'trans_type' => 'RENEW',
            'status' => $new_status,
            'created_at' => Carbon::now()->format('Y-m-d'),

        ];
        $new_term_id = PolicyTerm::addPolicyTerm($newData);

        if (isset($new_term_id)) {
            if ($term_data->term_end_date < Carbon::now()) {
                $policyDetails->term_id = $new_term_id;
                if ($request->paymentMethod == 'Cash' && $frequency == 1) {
                    $policyDetails->premium = $request->calculated_premium;
                } else {
                    $policyDetails->premium = ($request->term_permium) ? $request->term_permium : $request->first_premium;
                }
                $policyDetails->first_premium = $request->first_premium;
                $policyDetails->premium_freq = $frequency;
                $policyDetails->policyActivatedDate = $start_date;
                $policyDetails->billingStartDate = $billingDate;
                $policyDetails->vat = $vat;
                $policyDetails->vat_percent = $regionVat->vat;
                $policyDetails->term_start_date = $start_date;
                $policyDetails->term_end_date = $expiry_date;
                if (isset($policyRenewal)) {
                    $policyDetails->expiry_date = $policyRenewal->expiry_date;
                    $policyDetails->sum_assured = $policyRenewal->sum_assured;
                } else {
                    $policyDetails->expiry_date = NULL;
                    $policyDetails->sum_assured = NULL;
                }

                $term_id = null;
                if ($term_count == 0) {
                    $term_id = $newBusiness_term_id;
                } else {
                    $term_id = $term_data->id;
                }

                // if ($total_terms->isEmpty() && isset($term_id)) {
                    // $oldVehicleData = $this->uploadVehicleImages($request,$policyDetails->id);
                    $oldVehicleData = $this->getOldVehicleImages($policyDetails->id,$term_id);

                    if ($oldVehicleData) {
                        $vehicle = Vehicle::where('policy_id',$policyDetails->id)->first();
                        $vehicle->front = NULL;
                        $vehicle->back = NULL;
                        $vehicle->left = NULL;
                        $vehicle->right = NULL;
                        $vehicle->vehicleRegistration = NULL;
                        $vehicle->vehicle_valuation = NULL;
                        $vehicle->save();
                    }
                // }

                $document = new DocumentController();
                $generatePolicyDocument = $document->generatePolicyDocument($policyDetails->id);

                if ($generatePolicyDocument == true) {
                    $sentBy = 'System';
                    $getDocument =  $document->sendPolicyDocumentForRenew($policyDetails->id,$sentBy);
                }

            } else {
                if (isset($term_id)) {
                    $policyDetails->term_id = $term_id;
                }
            }

            $policyDetails->save();

            $policyRenewal = PolicyRenewal::where('policyNumber',$policyDetails->policyNumber)->orderBy('id', 'desc')->first();

            $policyRenewal->is_renewed = 1;
            $policyRenewal->save();
        }

        if ($policyDetails->save() && isset($new_term_id)) {
            return response()->json(['status' => 200, 'message' => 'Policy renewed succesfully.', 'policy_number' => $policyDetails->policyNumber ], 200);
        } else {
            //response for unsuccessful deletetion - (Important - status code for Flutter app)
            return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
        }
        }catch(Exception $e){
            return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
        }

    }

    public function getPolicyReinstateData($id)
    {
        $policy_reinstate = PolicyReinstate::where('policy_id',$id)->orderBy('id','desc')->get();
        return DataTables::of($policy_reinstate)
            ->addColumn('policyNumber', function ($policy_reinstate) {
                $policy = Policy::where('id',$policy_reinstate->policy_id)->first();
                $policyNumber = $policy->policyNumber;
                return $policyNumber;
            })
            ->editColumn('reinstated_by', function ($policy_reinstate) {
                $reinstated_by = "N/A";
                if (isset($policy_reinstate->reinstated_by)) {
                    $user = User::where('id',$policy_reinstate->reinstated_by)->first();
                    $reinstated_by = $user->firstName.' '.$user->lastName;
                }
                return $reinstated_by;
            })

            ->editColumn('reinstated_date', function ($policy_reinstate) {
                $reinstated_date = 'N/A';
                if (isset($policy_reinstate->reinstated_date)) {
                    $reinstated_date = \Carbon\Carbon::createFromFormat('Y-m-d', $policy_reinstate->reinstated_date)->format('d-m-Y');
                }
                return $reinstated_date;
            })

            ->editColumn('policyActivatedDate', function ($policy_reinstate) {
                $policyActivatedDate = 'N/A';
                if (isset($policy_reinstate->policyActivatedDate)) {
                    $policyActivatedDate = \Carbon\Carbon::createFromFormat('Y-m-d', $policy_reinstate->policyActivatedDate)->format('d-m-Y');
                }
                return $policyActivatedDate;
            })

            ->editColumn('policyCancelledDate', function ($policy_reinstate) {
                $policyCancelledDate = 'N/A';
                if (isset($policy_reinstate->policyCancelledDate)) {
                    $policyCancelledDate = \Carbon\Carbon::createFromFormat('Y-m-d', $policy_reinstate->policyCancelledDate)->format('d-m-Y');
                }
                return $policyCancelledDate;
            })

            ->editColumn('reinstate_type', function ($policy_reinstate) {
                $reinstate_type = 'N/A';
                if (isset($policy_reinstate->reinstate_type)) {
                    $reinstate_type = $policy_reinstate->reinstate_type;
                }
                return $reinstate_type;
            })

            ->editColumn('is_reinstate', function ($policy_reinstate) {
                $is_reinstate = NULL;
                if ($policy_reinstate->is_reinstate == 1) {
                    $is_reinstate = '<span class="kt-font-bold kt-font-accent">Yes</span>';
                } else {
                    $is_reinstate = '<span class="kt-font-bold kt-font-danger">No</span>';
                }

                return $is_reinstate;
            })

            ->editColumn('is_rerated', function ($policy_reinstate) {
                $is_rerated = NULL;
                if ($policy_reinstate->is_rerated == 1) {
                    $is_rerated = '<span class="kt-font-bold kt-font-accent">Yes</span>';
                } else {
                    $is_rerated = '<span class="kt-font-bold kt-font-danger">No</span>';
                }

                return $is_rerated;
            })

            ->rawColumns(['policyNumber', 'reinstated_by', 'reinstated_date', 'policyActivatedDate', 'policyCancelledDate', 'reinstate_type', 'is_reinstate', 'is_rerated'])
            ->make(true);
    }

    public function getPolicyTermsData($id)
    {
        $policy_term = PolicyTerm::where('policy_id',$id)->orderBy('id','desc')->get();
        return DataTables::of($policy_term)
            ->editColumn('term_start_date', function ($policy_term) {
                $term_start_date = \Carbon\Carbon::createFromFormat('Y-m-d', $policy_term->term_start_date)->format('d-m-Y');
                return $term_start_date;
            })

            ->editColumn('term_end_date', function ($policy_term) {
                $term_end_date = \Carbon\Carbon::createFromFormat('Y-m-d', $policy_term->term_end_date)->format('d-m-Y');
                return $term_end_date;
            })

            ->editColumn('status', function ($policy_term) {
                $status = NULL;
                if ($policy_term->status == 'Active') {
                    $status = '<span class="kt-font-bold kt-font-accent">Active</span>';
                } else {
                    $status = '<span class="kt-font-bold kt-font-danger">Deactive</span>';
                }

                return $status;
            })

            ->editColumn('frequency', function ($policy_term) {
                $frequency = NULL;
                if ($policy_term->frequency == 1){
                    $frequency = 'Monthly Installments - '. $policy_term->first_premium;
                } elseif ($policy_term->frequency == 2) {
                    $frequency = 'Three Installments in a year - '. $policy_term->first_premium;
                } elseif ($policy_term->frequency == 3) {
                    $frequency = 'Annual Installment - '. $policy_term->first_premium;
                } else {
                    $frequency = 'Not Found';
                }

                return $frequency;
            })

            ->addColumn('actions', function ($policy_term) {
                $actions = '';
                $actions .= '<a href="' . route('admin.policy.policyTermsView', $policy_term->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View">
                                <i class="la la-eye"></i>
                            </a>';

                if (auth::user()->hasPermissionTo('policy_term_dates_edit')) {
                    $actions .= '<a href="' . route('admin.policy.policyTermsEdit', $policy_term->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                }

                return $actions;
            })


            ->rawColumns(['term_start_date', 'term_end_date', 'status', 'frequency', 'actions'])
            ->make(true);
    }


    public function policyTermsView($id)
    {
        $policy_term = PolicyTerm::where('id',$id)->first();
        $policy = NULL;
        $vehicleOld = NULL;

        if (isset($policy_term)) {
            $policy = Policy::where('id',$policy_term->policy_id)->first();

            $vehicleOld = NULL;
            if ($policy_term->status == 'Active') {
                $vehicleOld = Vehicle::where('policy_id', $policy->id)->first();
            } else {
                $vehicleOld = vehicleOld::where('term_id', $id)->first();
            }
        }
        return view('admin.policy.policyTerms', compact('policy', 'policy_term', 'vehicleOld'));

    }

    public function policyTermsEdit($id)
    {
        $policy_term = PolicyTerm::where('id',$id)->first();
        $policy = NULL;
        $vehicleOld = NULL;

        if (isset($policy_term)) {
            $policy = Policy::where('id',$policy_term->policy_id)->first();

            $vehicleOld = NULL;
            if ($policy_term->status == 'Active') {
                $vehicleOld = Vehicle::where('policy_id', $policy->id)->first();
            } else {
                $vehicleOld = vehicleOld::where('term_id', $id)->first();
            }
        }
        return view('admin.policy.policyTermsEdit', compact('policy', 'policy_term', 'vehicleOld'));

    }

    public function policyTermUpdateData(Request $request)
    {
        try{
            $policy_term = PolicyTerm::where('id',$request->term_id)->first();
            if (isset($policy_term)) {
                $policy_term->term_start_date = Carbon::parse($request->term_start_date)->format('Y-m-d');
                $policy_term->term_end_date = Carbon::parse($request->term_end_date)->format('Y-m-d');
                // $policy_term->premium = $request->premium;
                // $policy_term->frequency = $request->frequency;
                // $policy_term->first_premium = $request->first_premium;
                // $policy_term->billing_start_date = Carbon::parse($request->billing_start_date)->format('Y-m-d');
                // $policy_term->policyActivatedDate = Carbon::parse($request->policyActivatedDate)->format('Y-m-d H:i:s');
                $policy_term->save();

                return redirect()->back()->with('success', 'Term updated successfully');
            } else {
                return redirect()->back()->with('error', 'Term not found');
            }

        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }

    }

    //mati verification link

    public function sendMatiVerificationLink(Request $request,$customer_id,$type)
    {
        try {
            $customer = Customer::where('id',$customer_id)->first(array('id','email','cellphone'));
               $data = [
                   'customer_id'=>$customer['id'],
                   'email'=>$customer['email'],
                   'cellphone'=>$customer['cellphone'],
               ];
            $products = Product::where('id',$request->product_id)->first('kyc_compliance');
            $compliance = KycCompliance::where('id', $products->kyc_compliance)->first(array('flow_id'));
               if($compliance != NULL && $compliance->flow_id != NULL)
                   $flow_id = $compliance->flow_id;
               else {
                   if(env('APP_STATUS') == 'Production')
                       $flow_id = '616ac99406694f001be574c7'; // AdvanceKYC LIVE
                   else
                       $flow_id = '612c87b1ebca36001b310ea1'; // LiveQuote Test
               }
               $data['flow_id'] = $flow_id;

            if($type == 'email'){
                if($data['email'] != null){
                    $markdown = new MatiLink($data);
                    $html = $markdown->render('Mail.MatiLink',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($data['email'],"Alphadirect | Upload documents for Kyc completion","",$html,null,[]));

                    return redirect()->back()->with('success', 'Verification link send on your email');
                }else{
                    return redirect()->back()->with('error', 'Email is not present');
                }

            }elseif($type == 'sms'){
                if($data['cellphone'] != null){
                   $customer_id = $data['customer_id'];
                   $phoneNumber = $data['cellphone'];
                   $sms = new SmsMessaging();
                   $sms->SendSMSMativerificationLink($phoneNumber,$flow_id,$customer_id);
                    return redirect()->back()->with('success', 'Verification link send on your cellphone');
                }else{
                    return redirect()->back()->with('error', 'Cellphone no. is not present');
                }
            }else{
                return redirect()->back()->with('error', 'Sorry! verification link not send');
            }
            $logData = [
                'customer_id'=>$data['customer_id'],
                'log_type'=>'email',
                'content_type'=>'kyc_compliance_email',
                'last_sent_date'=>Carbon::now()->format('Y-m-d'),
            ];
            $logData['next_send_date'] = Carbon::now()->addDays(1)->format('Y-m-d');
            $log = EmailSMSLogs::addLog($logData);

            }
            catch (Exception $e) {
                return response()->json(['status' => '401','message' => 'Please try again'], 401);
            }
    }

    // Mati Api for MobileApp
    public function MatiVerificationLink(Request $request,$type)
    {
        try {
                $policy_data = Policies::where('policyNumber', $request->policyNumber)->first(array('customer_id','policyNumber','product_id'));
                if( isset($policy_data) && $policy_data->policyNumber != null){
                    $customer = Customer::where('id',$policy_data->customer_id)->first(array('id','email','cellphone'));
                    $data = [
                        'customer_id'=>$customer['id'],
                        'email'=>$customer['email'],
                        'cellphone'=>$customer['cellphone'],
                    ];
                    $products = Product::where('id',$policy_data->product_id)->first('kyc_compliance');
                    $compliance = KycCompliance::where('id', $products->kyc_compliance)->first(array('flow_id'));
                    if($compliance != NULL && $compliance->flow_id != NULL)
                        $flow_id = $compliance->flow_id;
                    else {
                        if(env('APP_STATUS') == 'Production')
                            $flow_id = '616ac99406694f001be574c7'; // AdvanceKYC LIVE
                        else
                            $flow_id = '612c87b1ebca36001b310ea1'; // LiveQuote Test
                    }
                    $data['flow_id'] = $flow_id;

                    if($type == 'email'){
                        if($data['email'] != null){
                            $markdown = new MatiLink($data);
                            $html = $markdown->render('Mail.MatiLink',['data'=>$data]);
                            event(new \AlphaDirect\Events\SendMail($data['email'],"Alphadirect | Upload documents for Kyc completion","",$html,null,[]));
                            return response()->json(['status' => '200', 'message' => 'Verification link send on your email'], 200);
                        }else{
                            return response()->json(['status' => '401', 'message' => 'Email is not present'], 401);
                        }

                    }elseif($type == 'sms'){
                        if($data['cellphone'] != null){
                        $customer_id = $data['customer_id'];
                        $phoneNumber = $data['cellphone'];
                        $sms = new SmsMessaging();
                        $sms->SendSMSMativerificationLink($phoneNumber,$flow_id,$customer_id);
                        return response()->json(['status' => '200', 'message' => 'Verification link send on your cellphone'], 200);
                        }else{
                            return response()->json(['status' => '401', 'message' => 'Cellphone no. is not present'], 401);
                        }
                    }else{
                        return response()->json(['status' => '401', 'message' => 'Sorry! verification link not send'], 401);
                    }
                    $logData = [
                        'customer_id'=>$data['customer_id'],
                        'log_type'=>'email',
                        'content_type'=>'kyc_compliance_email',
                        'last_sent_date'=>Carbon::now()->format('Y-m-d'),
                    ];
                    $logData['next_send_date'] = Carbon::now()->addDays(1)->format('Y-m-d');
                    $log = EmailSMSLogs::addLog($logData);

              }else{
              return response()->json(['status' => '401', 'message' => 'Invalid Policy Number'], 401);
              }
            }
            catch (Exception $e) {
                return response()->json(['status' => '401','message' => 'Please try again'], 401);
            }
    }


    /*Reinstate section ****/

    public function ReinstatePolicy($id,$name)
    {
        try{
            $data = DB::table('policies')
                ->leftJoin('customer','customer.id','=','policies.customer_id')
                ->leftJoin('motor_comp_quotes','motor_comp_quotes.quoteNumber','=','policies.quoteNumber')
                ->leftJoin('customer_profile','customer_profile.customer_id','=','customer.id')
                ->leftJoin('vehicle','vehicle.policy_id','=','policies.id')
                //->leftJoin('policy_renewals', 'policy_renewals.policyNumber', 'policies.policyNumber')
                ->where('policies.id',$id)
                ->first();

                $data->priorAccidents = Claim::where('policy_id',$id)->count()+$data->priorAccidents;

                $policy_premium = Policy::where('id',$id)->first('premium');

                $premium = PolicyDiscountSurcharge::where('policy_id',$id)->orderBy('id','desc')->first('new_value');

               // dd($data,$name);
            $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));

            return view('admin.policy.reinstate',compact('data','agents','name','policy_premium','premium'));
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


    public function reinstateSetting()
    {
        $surcharge=Lookup::where('key','resinstate_surcharge')->first();
        $days=Lookup::where('key','days_to_resinstate')->first();
        return view('reinstate_setting', compact(['surcharge','days']));
    }

    public function Reinstate_areas($id)
    {
        try{
            $policy_data = Policies::where('id',$id)->first(array('id','customer_id','product_id'));

            if($policy_data->product_id == 3){
                $data = DB::table('policies')
                ->Join('customer','customer.id','=','policies.customer_id')
                ->leftJoin('motor_comp_quotes','motor_comp_quotes.quoteNumber','=','policies.quoteNumber')
                ->leftJoin('customer_profile','customer_profile.customer_id','=','customer.id')
               // ->leftJoin('policy_renewals', 'policy_renewals.policyNumber', 'policies.policyNumber')
                ->where('policies.id',$id)
                ->first(array('policies.id as policy_id','policies.policyNumber','policies.premium','customer.firstName','customer.middleName','customer.lastName','customer.email','customer.cellphone','customer_profile.omang','customer_profile.passport','customer_profile.gender','customer_profile.dob','customer_profile.customer_id','motor_comp_quotes.priorAccidents','motor_comp_quotes.quoteSent','motor_comp_quotes.quoteNumber'));

                $vehicle = Vehicle::where('policy_id',$policy_data->id)->first(array('is_imported','make','model'));
                $data->priorAccidents = Claim::where('policy_id',$id)->count()+$data->priorAccidents;
                $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));
                $balance= Ledger::where('policy_id', $id)->orderBy('id', 'desc')->first(array('balance'));
                // dd($data);
                $premium = PolicyDiscountSurcharge::where('policy_id',$id)->orderBy('id','desc')->first('new_value');
                return view('admin.policy.reinstate_areas',compact('data','agents','balance','vehicle','premium'));

            }else{
                $data = DB::table('policies')
                ->Join('customer','customer.id','=','policies.customer_id')
                ->leftJoin('customer_profile','customer_profile.customer_id','=','customer.id')
                ->where('policies.id',$id)
                ->first(array('policies.id as policy_id','policies.policyNumber','policies.premium','customer.firstName','customer.middleName','customer.lastName','customer.email','customer.cellphone','customer_profile.omang','customer_profile.passport','customer_profile.gender','customer_profile.dob','customer_profile.customer_id'));
                // dd($data);
                $vehicle = Vehicle::where('policy_id',$policy_data->id)->first(array('is_imported','make','model'));
                $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));
                $balance= Ledger::where('policy_id', $id)->orderBy('id', 'desc')->first(array('balance'));
                return view('admin.policy.reinstate_areas',compact('data','agents','balance','vehicle'));
            }
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }


    public function reinstateAccidentally($id,$name)
    {
        try{
            $policy_data = Policies::where('id',$id)->first(array('id','customer_id','product_id'));

            if($policy_data->product_id == 3){
                $data = DB::table('policies')
                ->Join('customer','customer.id','=','policies.customer_id')
                ->leftJoin('motor_comp_quotes','motor_comp_quotes.quoteNumber','=','policies.quoteNumber')
                ->leftJoin('customer_profile','customer_profile.customer_id','=','customer.id')
               // ->leftJoin('policy_renewals', 'policy_renewals.policyNumber', 'policies.policyNumber')
                ->where('policies.id',$id)
                ->first(array('policies.id as policy_id','policies.policyNumber','customer.firstName','customer.middleName','customer.lastName','customer.email','customer.cellphone','customer_profile.omang','customer_profile.passport','customer_profile.gender','customer_profile.dob','customer_profile.customer_id','motor_comp_quotes.priorAccidents','motor_comp_quotes.quoteSent','motor_comp_quotes.quoteNumber'));

                $vehicle = Vehicle::where('policy_id',$policy_data->id)->first(array('is_imported','make','model'));
                $data->priorAccidents = Claim::where('policy_id',$id)->count()+$data->priorAccidents;
                $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));
                $balance= Ledger::where('policy_id',$id)->latest()->first('balance');
                return view('admin.policy.reinstate_accidentally',compact('data','name','agents','balance','vehicle'));

            }else{
                $data = DB::table('policies')
                ->Join('customer','customer.id','=','policies.customer_id')
                ->leftJoin('customer_profile','customer_profile.customer_id','=','customer.id')
                ->where('policies.id',$id)
                ->first(array('policies.id as policy_id','policies.policyNumber','customer.firstName','customer.middleName','customer.lastName','customer.email','customer.cellphone','customer_profile.omang','customer_profile.passport','customer_profile.gender','customer_profile.dob','customer_profile.customer_id'));
                // dd($data,$id);
                $vehicle = Vehicle::where('policy_id',$policy_data->id)->first(array('is_imported','make','model'));
                $agents = User::where('active', 1)->orderBy('firstName')->get(array('id', 'firstName', 'lastName'));
                $balance= Ledger::where('policy_id',$id)->latest()->first('balance');
                return view('admin.policy.reinstate_accidentally',compact('data','name','agents','balance','vehicle'));
            }
        }catch(Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function setReinstate(request $request)
    {
        $reinstant=Lookup::where('key','resinstate_surcharge')->first();
        $reinstant->value=$request->surcharge;
        $reinstant->save();
        $reinstant=Lookup::where('key','days_to_resinstate')->first();
        $reinstant->value=$request->days;
        $reinstant->save();
         return redirect()->back()->with('success','Reinstant settings are updated successfully');
        // if(isset($request->surcharge))
        // {
        //      $reinstant=Lookup::where('key','resinstate_surcharge')->first();
        //     // dd($reinstant);
        //      $reinstant->value=$request->surcharge;
        //      $reinstant->save();
        //      return redirect()->back();
        // }elseif(isset($request->days)){
        //     $reinstant=Lookup::where('key','days_to_resinstate')->first();
        //    // dd($reinstant);
        //     $reinstant->value=$request->days;
        //     $reinstant->save();
        //     return redirect()->back();
        // }

    }


    public function reinstantPaymentMethod(request $request)
    {
               //dd($request);
        if ($request->action == 'cash') {

            $policy  = Policy::where('id', $request->policy_id)->first();
            $paymentDetails = PaymentTransaction::where('policyNumber',$policy->policyNumber)->get(array('id','referenceNumber','amount','paymentDate'));

            $banks   = Banks::all();
            $user    = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
            $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();

            if($banking != null && $banking->billing == 'RealPay')
            {
                $banking->bank_name   = Banks::where('bank_number', $banking->bankName)->value('bank_name');
                $banking->bank_branch = BankBranches::where('branch_id', $banking->branchCode)->value('name');
            }

            $new_premium     = $request->new_premium;
            $term_start_date = $request->term_start_date;
            $agent_id        = $request->agent_id;
            $balance         = $request->balance;

            if (isset($request->reinstate_type)) {
                $reinstate_type = $request->reinstate_type;
            } else {
                $reinstate_type = null;
            }

            return view('admin.policy.reinstantCashPayment',compact('policy','banks','user','banking','new_premium','term_start_date','agent_id','balance','reinstate_type','paymentDetails'));

        } elseif ($request->action == 'realpay') {
            $policy = Policy::where('id', $request->policy_id)->first();
            $banks  = Banks::all();
            $user = Customer::with(['profile', 'banking'])->where('id', $policy->customer_id)->first();
            $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
            if($banking != null && $banking->billing == 'RealPay')
            {
                $banking->bank_name   = Banks::where('bank_number', $banking->bankName)->value('bank_name');
                $banking->bank_branch = BankBranches::where('branch_id', $banking->branchCode)->value('name');
            }

            $new_premium     = $request->new_premium;
            $term_start_date = $request->term_start_date;
            $agent_id        = $request->agent_id;
            $balance         = $request->balance;

            if (isset($request->reinstate_type)) {
                $reinstate_type = $request->reinstate_type;
            } else {
                $reinstate_type = null;
            }

            return view('admin.policy.reinstateRealPayPayment',compact('policy','banks','user','banking','new_premium','term_start_date','agent_id','balance','reinstate_type'));
        }
    }

    public function addCashPaymentPolicyReinstate(request $request)
    {

        if (auth::user()->hasPermissionTo('offline-payments-list') || ('offline-payments-edit')) {

            try {
                $policy = Policy::where('policyNumber', $request->policyNumber)->first(array('id', 'policyNumber', 'customer_id', 'status', 'quoteNumber', 'product_id'));
                if ($policy->status != 1) {
                    $update = $this->updatePolicyDates($policy->policyNumber, 1);
                }
                //$customer = Customer::where('id',$policy->customer_id)->first(array('cellphone'));
                $entry = new PaymentTransaction();
                if (!isset($request->cashPaymentDoneReinstate) && $request->cashPaymentDoneReinstate != 1) {

                    $entry->policyNumber = $request->policyNumber;
                    $entry->referenceNumber = Carbon::now()->timestamp.'/'.$request->receiptNumber;
                    $entry->amount = $request->paymentAmount;
                    $entry->status = 'SUCCESS';
                    $entry->paymentDate = $request->paymentDate;
                    $entry->paymentMethod = 'CASH';
                    $entry->is_ledger = 0;
                    $entry->cashRecipient = $request->paymentRecievedBy;
                   // $entry->paymentFrequency = $request->paymentFreq;
                    $entry->numberOfInstalmentsPaid = $request->numberOfInstalmentsPaid;
                    $entry->note = $request->paymentNote;
                    $entry->paymentLoggedBy = auth()->user()->id;
                    if ($request->hasFile('payment_image')) {
                        $file = $request->file('payment_image');
                        $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
                        $filePath = 'PolicyPayment/' . $request->policyNumber . '-' . $entry->referenceNumber;
                        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $entry->payment_proof_link = $filePath;
                    }
                    $entry->save();
                }

                if ($entry->save()) {
                    $check = CustomerBanking::where('policy_id', $policy->id)->first();
                    if ($check == null) {
                        $banking = new CustomerBanking();
                    } else {
                        $banking = CustomerBanking::where('policy_id', $policy->id)->first();
                    }
                    $banking->customer_id = $policy->customer_id;
                    $banking->policy_id = $policy->id;
                    if (isset($request->addRealpay) && $request->addRealpay == 1)
                        $banking->billing = "RealPay";
                    $banking->billingCell = $request->billingCell;
                    $banking->bankName = $request->RPBanks;
                    $banking->branchCode = $request->RPBankBranch;
                    $banking->accountType = $request->accountType;
                    $banking->billingStartDate = $request->paymentStartDate;
                    $banking->accountNumber = $request->accountNumber;
                    $banking->billing_day = date("d", strtotime($request->paymentStartDate));
                    $banking->save();

                    if ($policy->product_id == 3) {
                        if ($policy->quoteNumber != null) {
                            $compQuote = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->first();

                            if ($request->paymentFreq != null && $compQuote != null) {
                                if ($request->paymentFreq == 1) {
                                    $premium = $compQuote->premiumMonthly;
                                } elseif ($request->paymentFreq == 2) {
                                    $premium = $compQuote->premium3Inst;
                                } elseif ($request->paymentFreq == 3) {
                                    $premium = $compQuote->premiumAnnually;
                                } else {
                                    return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Premium not found in Quote as per selected frequency');
                                    // return response()->json(['status' => '401', 'message' => 'Premium not found in Quote as per selected frequency']);
                                }
                                // $policy->billingStartDate = $request->paymentStartDate;
                                // $policy->billing_day = $banking->billing_day;
                                // $policy->premium = $premium;
                                // $policy->status = 1;
                                // if (isset($request->name) && $request->name == "Reinstate")
                                // {
                                //     $policy->trans_type='REINSTATE';
                                // }else{
                                //     $policy->trans_type='RENEW';
                                // }
                                // $policy->premium_freq = $request->paymentFreq;
                                // $policy->policyActivatedDate =  Carbon::now()->format("Y-m-d");
                                // $saved = $policy->save();

                                // $policy->billingStartDate = $request->paymentStartDate;
                                // $policy->billing_day = $banking->billing_day;
                                // $policy->premium = $premium;
                                // $policy->status = 1;
                                // if (isset($request->name) && $request->name == "Reinstate")
                                // {
                                //     $policy->trans_type='REINSTATE';

                                // }else{
                                //     $policy->trans_type='RENEW';
                                // }
                                // $policy->premium_freq = $request->paymentFreq;
                                // $policy->policyActivatedDate =  Carbon::now()->format("Y-m-d");
                                // $policy->save();
                            } else {
                                return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Payment frequency found null');

                                // return response()->json(['status' => '401', 'message' => 'Payment frequency found null.']);
                            }
                        } else {
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Quote number not found for this policy');

                            // return response()->json(['status' => '401', 'message' => 'Quote number not found for this policy']);
                        }
                    }

                    if (isset($request->addRealpay) && $request->addRealpay == 1 && $request->accountNumber  && $request->RPBanks  && $request->RPBankBranch  && $request->accountType) {
                        $request['policyID'] = $policy->id;
                        $realpayCon = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                        $cancelContract = $realpayCon->CancelRealpayPaymentContract($request);
                        if ($cancelContract->getData()->status == 200) {
                            $policy = Policy::where('id',$request->policyID)->orderBy('id','DESC')->first();
                        $customer = Customer::where('id', $policy->customer_id)->with('profile')->first();
                        $profile = CustomerProfile::where('customer_id',$customer->id)->first();
                        $customerBanking = CustomerBanking::where('customer_id',$policy->customer_id)->orderBy('id', 'DESC')->first();

                        // $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
                        // if (isset($data)) {
                        //     $request['premium'] = $data->new_value;
                        // } else {
                            $request['premium'] = $policy->premium;
                        // }

                        $fetchToken = $realpayCon->clientAuth();
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

                        $checkClient = $realpayCon->checkClientExists($policy->id);

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
                                    \"BankCode\": \"$banking->bankName\",\r\n
                                    \"BranchCode\": \"$banking->branchCode\",\r\n
                                    \"AccountType\": \"$banking->accountType\",\r\n
                                    \"AccountNumber\": \"$banking->accountNumber\",\r\n
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
                                    \"BankCode\": \"$banking->bankName\",\r\n
                                    \"BranchCode\": \"$banking->branchCode\",\r\n
                                    \"AccountType\": \"$banking->accountType\",\r\n
                                    \"AccountNumber\": \"$banking->accountNumber\",\r\n
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


                        //if(!empty($data['ClientPostResponse'][0]['Successful']) && empty($data['ClientPostResponse'][0]['Failed'])){
                            $customerBanking->bankName = $request->BankName;
                            $customerBanking->branchCode = $request->BranchCode;
                            $customerBanking->accountType = $request->accountType;
                            $customerBanking->accountNumber = $request->accountNumber;
                            $customerBanking->billing = "RealPay";
                            $customerBanking->billing_day = $request->billing_day;
                            $customerBanking->billingStartDate = $realpayCon->setDate($request->billing_day);
                            $customerBanking->save();

                            $fetchToken = $realpayCon->clientAuth();
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

                            if($request->paymentFreq != null){

                                if($request->paymentFreq == 2){
                                    $numberOfInstallments = '3';
                                    // $premium = ($request->premium - $request->first_premium) / 2;

                                    $premium = $request->premium / 3;
                                    $premium = round($premium,2);

                                    $firstCollectionAmount = $premium;
                                }
                                elseif($request->paymentFreq == 3){
                                    $frequency = 'YEAR';
                                    $numberOfInstallments = '1';
                                    // $premium = $request->premium;

                                    $premium = $request->premium;
                                    $firstCollectionAmount = $premium;

                                }
                                elseif($request->paymentFreq == 1){
                                    $numberOfInstallments = '12';
                                    // $premium = ($request->premium - $request->first_premium) / 11;

                                    $policyCon = new PolicyController();
                                    $premium = $policyCon->getMonthlyPrem(3,$request->premium);
                                    $premium = round($premium,2);
                                    $firstCollectionAmount = $premium;
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

                            // dd($premium);
                            // dd($request->all(),$request->paymentFreq,$numberOfInstallments);
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
                            $installmentStatus = null;
                            // dd($data);
                                if (isset($data['ContractPostResponse'][0]['Successful'][0]['ContractInstalments'])) {
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
                                    $contract = $realpayCon->storeContractDetails($data['ContractPostResponse'][0]['Successful'][0]);
                                    $installments = $realpayCon->storeInstallments($data['ContractPostResponse'][0]['Successful'][0]);
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

                                $request['new_premium'] = $request->premium;
                                // $request['first_premium'] = null;
                                $reinstatePolicy = $this->PolicyReinstate($request);
                                // dd($renewpolicy);
                                if ($reinstatePolicy->getData()->status == 200) {
                                    event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                                    // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                                    return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy reinstate succesfully');

                                } else {
                                    // return Redirect::back()->with('success', 'Failed to renew policy ' . $renewpolicy->getData()->message);
                                    return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to reinstate policy ' . $reinstatePolicy->getData()->message);

                                }

                                // return response()->json(['status' => '200', 'message' => 'Client added successfully on realpay', "instalmentStatus" => $installmentStatus,'premium' => $request->premium, 'first_premium' => $firstCollectionAmount], 200);

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

                                return redirect('admin/policy/policyView/'.$request->policy_id)->with('error', $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']);
                                // return response()->json(['status' => '401', 'message' => $data['ContractPostResponse'][0]['Failed'][0]['Failures'][0]['FailureDescription']], 401);

                            }
                        } else {
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error',$cancelContract->getData()->message);
                            // return response()->json(['status' => '401', 'message' => $cancelContract->getData()->message], 401);

                        }
                    } elseif (isset($request->cashPaymentDoneReinstate) && $request->cashPaymentDoneReinstate == 1) {
                        $cashPaymentAlreadyLog                      = PaymentTransaction::where('id', $request->selectPaymentDataReinstate)->orderBy('id','desc')->first();
                        $cashPaymentAlreadyLog->amountAfterRerating = null;
                        $cashPaymentAlreadyLog->paymentAlreadyLog   = 1;
                        $cashPaymentAlreadyLog->save();
                        $bankingData          = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
                        $bankingData->billing = 'cash';
                        $bankingData->save();

                        $request['new_premium'] = $request->paymentAmount;
                        $request['first_premium'] = null;
                        $reinstatePolicy = $this->PolicyReinstate($request);
                        // dd($renewpolicy);
                        if ($reinstatePolicy->getData()->status == 200) {
                            event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                            // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy reinstate succesfully');

                        } else {
                            // return Redirect::back()->with('success', 'Failed to renew policy ' . $renewpolicy->getData()->message);
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to reinstate policy ' . $reinstatePolicy->getData()->message);

                        }

                    } else {
                        $request['new_premium'] = $request->paymentAmount;
                        $request['first_premium'] = null;
                        $reinstatePolicy = $this->PolicyReinstate($request);
                        // dd($renewpolicy);
                        if ($reinstatePolicy->getData()->status == 200) {
                            event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                            // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Entry added without realpay payment.Policy reinstate succesfully');

                        } else {
                            // return Redirect::back()->with('success', 'Failed to renew policy ' . $renewpolicy->getData()->message);
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to reinstate policy ' . $reinstatePolicy->getData()->message);

                        }
                        // return redirect('admin/policy/policyView/'.$request->policy_id)->with('error', 'Entry added without realpay payment');

                        // return response()->json(['status' => '200', 'message' => 'Entry added without realpay payment','premium' => $request->premium, 'first_premium' => null]);
                    }
                } else {
                    return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Problem storing data in payment transaction table');

                    // return response()->json(['status' => '401', 'message' => 'Problem storing data in payment transaction table']);
                }

            } catch (Exception $e) {
                return redirect('admin/policy/policyView/'.$request->policy_id)->with('error',$e->getMessage().' '.$e->getLine());

                // return response()->json(['status' => '401', 'message' => $e->getMessage().' '.$e->getLine()]);
            }
        }
        else{
            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Sorry! You do not have permission to access this page!');

            // return response()->json(['status' => '401', 'message' => 'Sorry! You do not have permission to access this page!']);
        }
    }




    public function addRealPayPaymentPolicyReinstate(request $request)
    {
        if (auth::user()->hasPermissionTo('offline-payments-list') || ('offline-payments-edit')) {
            try {
                $policy = Policy::where('policyNumber', $request->policyNumber)->first(array('id', 'policyNumber', 'customer_id', 'status', 'quoteNumber','premium'));
                if ($policy->status != 1) {
                    $update = $this->updatePolicyDates($policy->policyNumber, 1);
                }

                if ($request->accountNumber  && $request->BankName  && $request->BranchCode  && $request->accountType) {
                    $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $data = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
                    if (isset($data)) {
                        $request['premium'] = $data->new_value;
                    } else {
                        $request['premium'] = $policy->premium;
                    }

                    // dd($request->all());
                    $realpayPayment = $realpay->logRealpayPaymentForPolicyRenewal($request);
                    // dd($realpayPayment);
                    if ($realpayPayment->getData()->status == 200) {
                        $request['new_premium'] = $realpayPayment->getData()->premium;
                        $request['first_premium'] = $realpayPayment->getData()->first_premium;
                        $policyCon = new PolicyController();
                        $reinstatePolicy = $policyCon->PolicyReinstate($request);
                        // dd($renewpolicy);
                        if ($reinstatePolicy->getData()->status == 200) {
                            event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                            // return Redirect::back()->with('success', 'Policy renewed succesfully.');
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('success','Policy reinstate succesfully');

                        } else {
                            // return Redirect::back()->with('success', 'Failed to renew policy ' . $renewpolicy->getData()->message);
                            return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to reinstate policy ' . $reinstatePolicy->getData()->message);

                        }
                        // return response()->json(['status' => '200', 'message' => 'Entry added sucessfully', 'instalmentStatus' => $realpayPayment->getData()->instalmentStatus]);
                    } else {
                        return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Failed to add realpay payment');

                        // return response()->json(['status' => '401', 'message' => 'Failed to add realpay payment']);
                    }
                } else {
                    return redirect('admin/policy/policyView/'.$request->policy_id)->with('error','Something went wrong');

                    // return response()->json(['status' => '401', 'message' => 'Something went wrong']);
                }
            } catch (Exception $e) {
                //            \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['status' => '401', 'message' => $e->getMessage().' '.$e->getLine()]);

                // return redirect()->back()->with('error', $e->getMessage());
            }
        } else {
            return response()->json(['status' => '401', 'message' => 'Sorry! You do not have permission to access this page!']);

            // return redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }


    // public function PolicyReinstate($request)
    // {
    //     //dd($request);
    //     try{
    //             $policyDetails=Policy::where('id',$request->policy_id)->first();
    //             if ($policyDetails == null) {
    //                 return response()->json(['status' => '401','message' => 'Policy not available for edit.'], 401);
    //             }

    //              $start_date=\Carbon\Carbon::createFromFormat('Y-m-d',$request->term_start_date)->format('Y-m-d');
    //              $expiry_date=\Carbon\Carbon::createFromFormat('Y-m-d',$request->term_end_date)->addYear()->format('Y-m-d');

    //         $status = 'Deactive';
    //         if (isset($policyDetails->expiry_date)) {
    //             if ($request->paymentMethod == 'Cash') {
    //                 if ($policyRenewal->expiry_date < Carbon::now()) {
    //                     $status = 'Deactive';
    //                 } else {
    //                     $status = 'Active';
    //                 }
    //             } elseif ($request->paymentMethod == 'Realpay' && $request->instalment_status != NULL) {
    //                 if ($policyRenewal->expiry_date < Carbon::now()) {
    //                     $status = 'Deactive';
    //                 } else {
    //                     $status = 'Active';
    //                 }
    //             }
    //         }

    //         $billingDate = NULL;
    //         $frequency = NULL;
    //         if ($request->paymentMethod == 'Cash') {
    //             $billingDate = $request->paymentDate;
    //             $frequency = $request->paymentFreq;
    //         } elseif ($request->paymentMethod == 'Realpay') {
    //             $billingDate = $request->billingDay;
    //             $frequency = $request->frequency;
    //         }

    //             // $term_start_date=\Carbon\Carbon::now()->format('Y-m-d');
    //             // $term_end_date=\Carbon\Carbon::now()->addYear()->format('Y-m-d');


    //         }catch(Exception $e){

    //     }
    // }

    public function policyLifeCycleData($id)
    {

        $data=PolicyLifecycle::where('policy_id',$id)->get();
        //dd($data);
        return DataTables::of($data)
            // ->editColumn('term_start_date', function ($data) {
            //     $term_start_date = \Carbon\Carbon::createFromFormat('Y-m-d', $data->term_start_date)->format('d-m-Y');
            //     return $term_start_date;
            // })

            // ->editColumn('term_end_date', function ($data) {
            //     $term_end_date = \Carbon\Carbon::createFromFormat('Y-m-d', $data->term_end_date)->format('d-m-Y');
            //     return $term_end_date;
            // })

            ->editColumn('status', function ($data) {
                $status = NULL;
                if ($data->status == 'Active') {
                    $status = '<span class="kt-font-bold kt-font-accent">Active</span>';
                } else {
                    $status = '<span class="kt-font-bold kt-font-danger">Deactive</span>';
                }

                return $status;
            })

            ->editColumn('frequency', function ($data) {
                $frequency = NULL;
                if ($data->frequency == 1){
                    $frequency = 'Monthly Installments';
                } elseif ($data->frequency == 2) {
                    $frequency = 'Three Installments in a year';
                } elseif ($data->frequency == 3) {
                    $frequency = 'Annual Installment';
                } else {
                    $frequency = 'Monthly Installments'; //if Not Found means Monthly
                }
                return $frequency;
            })

            ->editColumn('billingStartDate',function($data){
                if($data->billing_start_date != null)
                {
                    $data->billing_start_date= \Carbon\Carbon::createFromFormat('Y-m-d', $data->term_start_date)->format('d-m-Y');
                }else{
                    $data->billing_start_date=Null;
                }
                return $data->billing_start_date;
            })

            ->editColumn('actionBy',function($data){
                $actionBy = NULL;
                if($data->action_user != null)
                {
                    $actionBy= 'User - '.$data->User->firstName.' '.$data->User->lastName;
                }
                elseif($data->action_customer != null)
                {
                    $actionBy= 'Customer - '.$data->Customer->firstName.' '.$data->Customer->lastName;
                }
                else{
                    $actionBy=Null;
                }
                return $actionBy;
            })

            ->editColumn('actionDate',function($data){
                if($data->created_at != null)
                {
                    $data->created_at=$data->created_at;
                    // $data->created_at= \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)->format('d-m-Y');
                }else{
                    $data->created_at=Null;
                }
                return $data->created_at;
            })


            ->editColumn('action', function ($data) {

                return $data->action;
            })

            ->rawColumns(['status','frequency','actionBy','action'])
            ->make(true);

    }

    public function generateAndSendRenewalLink($policyNumber){
        try{
            $new_premium = PolicyRenewal::where('policyNumber', $policyNumber)->orderBy('id', 'desc')->first('new_premium');

            if($new_premium != null){
                $amount = $new_premium->new_premium;
            }else{
                $amount = null;
            }

            $link = $this->generateSendPaymentURL($policyNumber,null,'renew',$amount);
            $policy = Policy::where('policyNumber', $policyNumber)->first(array('id', 'customer_id','policyNumber'));
            $customer = Customer::where('id', $policy->customer_id)->first(array('id','email', 'cellphone'));
            $data = [
                'customer_id'=> $customer['id'],
                'email'=> $customer['email'],
                'cellphone'=> $customer['cellphone'],
                'link'=> $link,
            ];

            $urlValue = \Config::get('values.graphite_url'); //get Graphite Url from env file
            $paymentUrl = new PaymentUrls(); //generate the payment url and save it first
            $paymentUrl->cellphone = $customer['cellphone']; //get the details
            $paymentUrl->policy_id = $policy->id;
            $paymentUrl->amount = $amount;
            $paymentUrl->created_by = auth()->user()->id;
            $paymentUrl->request_from = 'graphite';
            $paymentUrl->note = null;
            $paymentUrl->link_type = 'Renew';
            $paymentUrl->status = 0;
            $paymentUrl->url = $link; //payment URL
            $paymentUrl->save();

            if($customer != NULL){
                if($customer->email != null){
                    $markdown = new RenewPolicy($data);
                    $html = $markdown->render('Mail.RenewPolicy',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($customer->email,"Alphadirect |  Use this link for Renewal","",$html,null,['policyNumber' => $policy->policyNumber]));
                    return redirect()->back()->with('success', 'Renew link sent on your email and sms.');
                } elseif ($customer->cellphone != null) {
                    if(env('APP_STATUS') == 'Production')
                        $tempId = 40;
                    else
                        $tempId = 42;

                    $sms = new SmsMessaging();
                    $sms = $sms->sendOneTimePaymentLinkRenewal($tempId,$customer->cellphone,$link,$policy->policyNumber,$amount);
                    return redirect()->back()->with('success', 'Renew link sent on your sms.');
                } else{
                    return redirect()->back()->with('error', 'Email and Cellphone is not present');
                }
            }
            return redirect()->back()->with('success','Link generated successfully!');
        }catch(\Exception $ex){
            return redirect()->back()->with('error','Something went wrong');
        }
    }

    public function policyCancelledAccidently($id){
        $policy=Policy::find($id);
        $policy->status= 0;
        $policy->save();
        return redirect()->back()->with('success','Policy activated successfully');
        //dd($policy);
    }

    public function  getInstalmentsRealpay(Request $request){
        if($request->env == 'Development') {
            $env = "https://realpaycollect.com:4448/rpt/rpws/";
            $clientAuth = "TUkxaTVIMHNUaVIybVdsdkRmdkNzdy4uOnFFaE01VFdMa2FvYXZpMnBxWVM5T3cuLg==";
        }else if($request->env == 'Production') {
            $env = "https://realpaycollect.com:4448/rpp/rpws/";
            $clientAuth = "QW5lcTR4d1ZpVWJLS0VhUUpGVjI5QS4uOkxmUVp1aGFLZF9CYkFqUnZXTXp6b1EuLg==";
        }else {
            dd('ENV not found');
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $env."/oauth/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Basic ".$clientAuth,
                "Content-Type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response,true);
        $token = $response['token_type'].' '.$response['access_token'];



        $curl = curl_init();

        curl_setopt_array($curl, array(
                    CURLOPT_URL => $env . "/maintain/contracts/" . $request->product . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),
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

                dd($details);

//
//                dd($env . "/maintain/contracts/" . $request->product . "?ClientNumber=" . $request->clientNumber . "&ContractNumber=" . $request->contractNumber . "&BeneficiaryUser=" . env('REALPAY_MERCHANT') . "&Version=" . env('REALPAY_VERSION'),$token);

//                if($contractData != null && !empty($contractData)){
//
//                    $rlpayClientContracts = RealpayClientContracts::where('client_number',$request->clientNumber)
//                        ->where('policy_id',$policy->id)
//                        ->where('contract_number',$request->contractNumber)
//                        ->get();
//                    if (json_decode($rlpayClientContracts) == NULL) {
//                        $data = [
//                            "policy_id"=> $policy->id,
//                            "client_number"=> $request->clientNumber,
//                            "contract_number"=> $request->contractNumber,
//                            "rate_id"=> NULL,
//                            "status"=> 1,
//                        ];
//                        $addRealpayClientContracts = RealpayClientContracts::addLog($data);
//                    }
//
//                    $data = $contractData[0];
//                    $log = RealpayLogs::where('policy_id',$policy->id)->orderBy('id','desc')
//                        ->first();
//                    if($log != null){
//                        $log->status = 1;
//                        $log->save();
//                    }else{
//                        $addL = new RealpayLogs();
//                        $addL->policy_id = $data['ContractNumber'];
//                        $addL->event = 1;
//                        $addL->status = 1;
//                        $addL->save();
//                    }
//
//                    $req = RealpayPaymentRequest::where('clientNumber',$policy->policyNumber)
//                        ->orderBy('id','desc')
//                        ->first();
//                    if($req != null){
//                        $req->status = 1;
//                        $req->save();
//                    }else{
//                        $addR = new RealpayPaymentRequest();
//                        $addR->policy_id = $data['ContractNumber'];
//                        $addR->clientNumber = $data['ClientNumber'];
//                        $addR->client_response_sequence = $data['ContractNumber'];
//                        $addR->clientCreated = 1;
//                        $addR->contractCreated = 1;
//                        $addR->first_premium = $data['ContractInstalments'][0]['InstalmentAmount'];
//                        $addR->premium = $data['ContractInstalments'][2]['InstalmentAmount'];
//                        $addR->billing_day = $policy->billing_day;
//                        $addR->billing_date = $data['InstalmentStartDate'];
//                        $addR->first_premium_contract = '';
//                        $addR->contract = $data['ContractNumber'];
//                        $addR->contract_response_sequence = '';
//                        $addR->frequency = $policy->premium_freq;
//                        $addR->response = 1;
//                        $addR->status = 1;
//                        $addR->save();
//                    }
//                    $contracts = RealpayContractDetails::where('ContractSequence',$data['ContractSequence'])->first();
//
//                    if($contracts == null){
//                        $addC = new RealpayContractDetails();
//                        $addC->ContractSequence = $data['ContractSequence'];
//                        $addC->ClientNumber = $data['ClientNumber'];
//                        $addC->ContractNumber = $data['ContractNumber'];
//                        $addC->CTCPercentage = $data['CTCPercentage'];
//                        $addC->InstalmentStartDate = $data['InstalmentStartDate'];
//                        $addC->TrackingCode = $data['TrackingCode'];
//                        $addC->NumberOfInstalments = $data['NumberOfInstalments'];
//                        $addC->FrequencyCode = $policy->premium_freq;
//                        $addC->CollectionDay = $policy->billing_day;
//                        $addC->status = null;
//                        $addC->save();
//                    }
//
//                    $ins = RealpayContractInstallments::where('clientNumber',$policy->policyNumber)->count();
//                    if($ins == 0 || $ins == null){
//                        $storeIns = $this->storeInstallments($data);
//                    }
//
//                    \Illuminate\Support\Facades\DB::commit();
//                    return redirect()->route('admin.view-installments', $policy->id);
//                }else{
//                    \Illuminate\Support\Facades\DB::rollBack();
//                    return \Illuminate\Support\Facades\Redirect::back()->with('error','Data found empty');
//                }


//        }catch(\Exception $e){
//            \Illuminate\Support\Facades\DB::rollBack();
//            return \Illuminate\Support\Facades\Redirect::back()->with('error',$e->getMessage());
//        }

    }

    public function isJSON($string){
        return is_string($string) && is_array(json_decode($string, true))  && (json_last_error() == JSON_ERROR_NONE) ? true : false;
     }

     public function transactionInfo(Request $request){
        try{
            if($request->token) {
                $link = OneTimePaymentURL::where('token',$request->token)->orderBy('id','desc')->first();
                if(!$link->referenceNumber){
                    $mobC = new MobC;
                    // dd($mobC);
                   # ->generate_string();
                   # ->saveReferenceNumber($referenceNumber, $link->policyNumber);
                }
                    $transaction = OneTimePaymentURL::where('referenceNumber', $link->referenceNumber)
                        ->first(['id','policyNumber','referenceNumber','amount','status']);
                    $transaction['token_status']       = $link->status;
                    $transaction['newReferenceNumber'] = $link->status;
                    return response()->json([
                        'status'      => 'success',
                        'message'     => 'Response successful',
                        'transaction' => $transaction
                    ],200);

            }else{
                return response()->json(['status' => 'failed','transaction'=>null,'message' => 'Token information not found'],401);
            }
        }catch(\Exception $ex){
            return response()->json(['status' => 'failed','transaction'=>null,'message' => $ex->getMessage()],401);
        }
     }

      public function PolicyReinstate(Request $request)
    {
        try {
            // dd($request->all());
            if (!isset($request->paymentFreq)) {
                $request->paymentFreq = $request->frequency;
            }

            if (!isset($request->paymentDate)) {
                $request->paymentDate = Carbon::now()->format('Y-m-d');
            }

            if (!isset($request->billingDay)) {
                $request->billingDay = Carbon::now()->format('Y-m-d');
            }


            if (!isset($request->policy_id)) {
                $request->policy_id = $request->policyID;
            }

            $policyDetails = Policy::where('id', $request->policy_id)->first();
            // dd($policyDetails);
            if ($policyDetails == null) {
                return response()->json(['status' => '401','message' => 'Policy not available for edit.'], 401);
            }

            $motor_comp_quotes = MotorComprehensiveQuotes::where('quoteNumber',$policyDetails->quoteNumber)->orderBy('id','desc')->first();
            $rerated_premium_quotes = null;
            if (isset($motor_comp_quotes)) {
                $rerated_premium_quotes = ReratedPremiumQuote::where('rate_id',$motor_comp_quotes->ratings_id)->orderBy('id','desc')->first();
                if(isset($rerated_premium_quotes)){
                    $policyDetails->premium       = $rerated_premium_quotes->old_premium;
                    $policyDetails->premium_freq  = $rerated_premium_quotes->old_frequency;
                    $policyDetails->first_premium = $rerated_premium_quotes->old_first_premium;
                    $policyDetails->sum_assured   = $rerated_premium_quotes->old_sum_insured;
                }
            }

            if(isset($policyDetails->premium_freq) && isset($policyDetails->premium)){
                $data = [
                    'premium'      => $policyDetails->premium,
                    'premium_freq' => $policyDetails->premium_freq
                ];

                $policyCon = new PolicyController();
                $premium = $policyCon->getMotorComprehensivePolicyPremium($data);

                if($policyDetails->premium_freq == 2){
                    $policyDetails->premium = $premium['3_inst'];

                }
                elseif($policyDetails->premium_freq == 3){
                    $policyDetails->premium = $premium['annual'];

                }
                elseif($policyDetails->premium_freq == 1){
                    $policyDetails->premium = $premium['monthly'];
                }
            }

            $policyReinstate = PolicyActivateCancelledDate::where('policyNumber',$policyDetails->policyNumber)->whereNotNull('cancelled_date')->orderBy('id','desc')->first();
            $policyLifeCycle = PolicyLifecycle::where('policy_id',$policyDetails->id)->where('action','Cancel')->orderBy('id','desc')->first();

            $cancelled_date = null;
            if (isset($policyReinstate)) {
                $cancelled_date = $policyReinstate->cancelled_date;
            } elseif (isset($policyLifeCycle)) {
                $cancelled_date = $policyLifeCycle->created_at;
            } else{
                $cancelled_date = $policyDetails->updated_at;
            }

            $start_date  = Carbon::now()->format('Y-m-d');
            $expiry_date = Carbon::now()->addYear()->format('Y-m-d');

            $tranaction = Transaction::where('policyNumber',$policyDetails->policyNumber)->orderBy('id','desc')->first();
            $status = 'Deactive';

            // if (isset($policyRenewal->expiry_date)) {
            //     if ($request->paymentMethod == 'Cash') {
            //         if ($policyRenewal->expiry_date < Carbon::now()) {
            //             $status = 'Deactive';
            //         } else {
            //             $status = 'Active';
            //         }
            //     } elseif ($request->paymentMethod == 'Realpay' && isset($tranaction) && $tranaction == 'A') {
            //         if ($policyRenewal->expiry_date < Carbon::now()) {
            //             $status = 'Deactive';
            //         } else {
            //             $status = 'Active';
            //         }
            //     }
            // }

            $billingDate = NULL;
            $frequency = NULL;
            if ($request->paymentMethod == 'Cash') {
                $billingDate = $request->paymentDate;
                $frequency = $request->paymentFreq;
            } elseif ($request->paymentMethod == 'Realpay' || $request->payment_method == 'RealPay') {
                $billingDate = $request->billingDay;
                $frequency = $request->frequency;
            } elseif ($request->paymentMethod == 'DPO' || $request->payment_method == 'DPO') {
                $billingDate = $request->billingDay;
                $frequency = $request->frequency;
            }

            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $oldTermPaymentMethod = $realpay->checkPaymentMethod($request->policy_id);

            $balance_due = null;
            if (isset($request->balance_due)) {
                $balance_due = $request->balance_due;
            }

            //policy term
            // policy old term data
            $term_count = PolicyTerm::where('policy_id',$request->policy_id)->whereIn('trans_type',['NEW BUSINESS','REINSTATE'])->count();

            // $total_terms = PolicyTerm::where('policy_id',$request->policy_id)->where('trans_type','REINSTATE')->get();
            if ($term_count == 0) {
                $data = [
                    'policy_id' => $request->policy_id,
                    'term_start_date' => ($policyDetails->policyActivatedDate) ? $policyDetails->policyActivatedDate : $policyDetails->created_at,
                    'term_end_date' => $policyReinstate->cancelled_date,
                    'premium' => $policyDetails->premium,
                    'annual_premium' => $policyDetails->premium,
                    'renewed_by' => NULL,
                    'renewals_date' => NULL,
                    'frequency' => $policyDetails->premium_freq,
                    'first_premium' => $policyDetails->first_premium,
                    'billing_start_date' => $policyDetails->billingStartDate,
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $policyDetails->policyActivatedDate,
                    'payment_method' => $oldTermPaymentMethod,
                    'payment_reference' => $request->policy_id,
                    'trans_type' => 'NEW BUSINESS',
                    'balance_due' => NULL,
                    'status' => $status

                ];
                $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
                // $term_id = PolicyTerm::addPolicyTerm($data);
            }

        // policy new term data

        $new_status = 'Active';

        // if ($request->paymentMethod == 'Cash') {
        //     if ($status == 'Deactive') {
        //         if ($expiry_date > Carbon::now()) {
        //             $new_status = 'Deactive';
        //         } else {
        //             $new_status = 'Active';
        //         }
        //     }
        // } elseif ($request->paymentMethod == 'Realpay' && isset($tranaction) && $tranaction == 'A') {
        //     if ($status == 'Deactive') {
        //         if ($expiry_date > Carbon::now()) {
        //             $new_status = 'Deactive';
        //         } else {
        //             $new_status = 'Active';
        //         }
        //     }
        // }

        if ($request->reinstate_type == 'reinstate_fresh') {
            $policyData = Policy::where('id', $request->policy_id)->first();
            $request->first_premium = $policyData->first_premium;
        }

        $term_data = PolicyTerm::where('policy_id',$request->policy_id)->whereIn('trans_type',['NEW BUSINESS','REINSTATE'])->orderBy('id','desc')->first('id');

        $newData = [
            'policy_id' => $request->policy_id,
            'term_start_date' => $start_date,
            'term_end_date' => $expiry_date,
            'premium' => ($request->term_permium) ? $request->term_permium : $request->first_premium,
            'annual_premium' => $request->new_premium,
            'renewed_by' => NULL,
            'renewals_date' => NULL,
            'frequency' => $frequency,
            'first_premium' => $request->first_premium,
            'billing_start_date' => $billingDate,
            'policy_documents' => NULL,
            'policyActivatedDate' => $start_date,
            'payment_method' => $request->paymentMethod,
            'payment_reference' => $request->policy_id,
            'trans_type' => 'REINSTATE',
            'balance_due' => $balance_due,
            'status' => $new_status

        ];
        $new_term_id = PolicyTerm::addPolicyTerm($newData);

        if (isset($new_term_id)) {
            // if ($policyRenewal->expiry_date < Carbon::now()) {
                $policyDetails->term_id = $new_term_id;
                if ($request->paymentMethod == 'Cash' && $frequency == 1) {
                    $policyDetails->premium = $request->calculated_premium;
                } else {
                    $policyDetails->premium = ($request->term_permium) ? $request->term_permium : $request->first_premium;
                }

                if ($request->reinstate_type == 'Reinstate_arrears') {
                    $policyDetails->first_premium = $policyDetails->first_premium;
                } else {
                    if ($request->reinstate_type == 'reinstate_fresh') {
                        $policyData = Policy::where('id', $request->policy_id)->first();
                        $policyDetails->first_premium = $policyData->first_premium;
                        $policyDetails->sum_assured = $policyData->sum_assured;
                    } else {
                        $policyDetails->first_premium = $request->first_premium;
                    }
                }
                $policyDetails->premium_freq = $frequency;
                $policyDetails->policyActivatedDate = $start_date;
                $policyDetails->billingStartDate = $billingDate;

                // $policyDetails->term_start_date = $start_date;
                // $policyDetails->term_end_date = $expiry_date;
                // if (isset($policyRenewal)) {
                //     $policyDetails->expiry_date = $policyRenewal->expiry_date;
                //     $policyDetails->sum_assured = $policyRenewal->sum_assured;
                // } else {
                //     $policyDetails->expiry_date = NULL;
                //     $policyDetails->sum_assured = NULL;
                // }

                // if (isset($term_id)) {
                //     $request['term_id'] = $term_id;
                // }

                $term_id = null;
                if ($term_count == 0) {
                    $term_id = $newBusiness_term_id;
                } else {
                    $term_id = $term_data->id;
                }


                if ($policyDetails->product_id == 3 || $policyDetails->product_id == 2) {
                    // $oldVehicleData = $this->uploadVehicleImages($request,$policyDetails->id);
                    $oldVehicleData = $this->getOldVehicleImages($policyDetails->id,$term_id);
                    if ($oldVehicleData) {
                        $vehicle = Vehicle::where('policy_id',$policyDetails->id)->first();
                        $vehicle->front = NULL;
                        $vehicle->back = NULL;
                        $vehicle->left = NULL;
                        $vehicle->right = NULL;
                        $vehicle->vehicleRegistration = NULL;
                        $vehicle->vehicle_valuation = NULL;
                        $vehicle->save();
                    }
                }

            // } else {
            //     if (isset($term_id)) {
            //         $policyDetails->term_id = $term_id;
            //     }
            // }


            if ($request->payment_method == 'DPO') {

                $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policyDetails->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policyDetails->customer_id,
                ];


                //fire event send sms and email when policy is created
                $event                                       = VerifyTokenEvent::dispatch($data);
                // dd($event);
                $event                                       = $event[0];
                $paymentTransaction                          = new PaymentTransaction();
                $paymentTransaction->policyNumber            = $policyDetails->policyNumber;
                $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
                $paymentTransaction->amount                  = isset($event['amount']) ? $event['amount'] : 0.00;
                $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
                $paymentTransaction->paymentDate             = Carbon::now();
                $paymentTransaction->paymentMethod           = 'DPO';
                $paymentTransaction->numberOfInstalmentsPaid = 0;
                $paymentTransaction->paymentFrequency        = isset($request->payment_frequency) ?  $request->payment_frequency : 1;
                $paymentTransaction->TransID                 = isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null;
                $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
                $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
                $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
                $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
                $paymentTransaction->note                    = 'policy reinstate';
                $paymentTransaction->save();
                $policyController = new PolicyController(); //call the action function to update the status of the Policy to active
                if( $event['status'] == 1 )
                {
                    $action                = $policyController->action($policyDetails->id, 1, 'DPO');  //set the Policy status to active
                    $policyRenewalArr = [
                        'id'               => $policyDetails->id,
                        'policyNumber'     => $policyDetails->policyNumber,
                        'product_id'       => $policyDetails->product_id,
                        'premium_freq'     => $policyDetails->premium_freq,
                        'latestSchedule'   => ScheduleTransaction::where('policy_id', $policyDetails->policy_id)->orderBy('installment', 'desc')->value('installment'),
                        'premium'          => $paymentTransaction->amount,
                        'customer_id'      => $policyDetails->customer_id,
                        'email'            => Customer::where('id', $policyDetails->customer_id)->value('email'),
                        'billingStartDate' => $policyDetails->billingStartDate,
                        'reinstate_type' => $request->reinstate_type,
                        'regular_permium' => ($request->term_permium) ? $request->term_permium : $request->first_premium,
                    ];

                    RenewPolicySchedulesEvent::dispatch($policyRenewalArr);
                }
            }

            $reinstated_by = isset($request->reinstated_by) ? $request->reinstated_by : null;
            $policyActivatedDate = Carbon::parse($policyDetails->policyActivatedDate)->format('Y-m-d');
            $activatedCancelledDates = PolicyActivateCancelledDate::where('policyNumber',$policyDetails->policyNumber)->whereNotNull('cancelled_date')->orderBy('id','desc')->first();
            $data = [
                'policy_id' => $request->policy_id,
                'reinstated_by' => isset(auth()->user()->id) ? auth()->user()->id : $reinstated_by,
                'reinstated_date' => Carbon::now()->format('Y-m-d'),
                'policyActivatedDate' => ($activatedCancelledDates->activated_date) ? $activatedCancelledDates->activated_date : $policyActivatedDate,
                'policyCancelledDate' => $activatedCancelledDates->cancelled_date,
                'reinstate_type' => $request->reinstate_type,
                'regular_premium' => $request->first_premium,
                'is_reinstate' => 1,
                'is_rerated' => ($request->reinstate_type == 'reinstate_fresh') ? 1 : 0,
            ];
            $reinstated = PolicyReinstate::addPolicyReinstated($data);

            $payment_link = OneTimePaymentURL::where('policyNumber',$policyDetails->policyNumber)->orderBy('id','desc')->first();
            // dd($payment_link);
            if(isset($payment_link)){
                $payment_link->status = 1;
                $payment_link->save();
            }

            $payemnt_url = PaymentUrls::where('policy_id',$policyDetails->id)->orderBy('id','desc')->first();
            if (isset($payemnt_url)) {
                $payemnt_url->status = 1;
                $payemnt_url->save();
            }

            $policyDetails->is_reinstate = 1;
            $policyDetails->status = 1;
            $policyDetails->save();
        }

        if ($request->payment_method == 'DPO' && $request->leadSource = 'pay.alphadirect.co.bw') {

            if ($policyDetails->save() && isset($new_term_id) && $paymentTransaction->status == 'SUCCESS') {
                event(new \AlphaDirect\Events\policyLifecycle($policyDetails->id , "Renewed"));
                return redirect(env('TestPay_URL').'thankyou_reinstate'); //PAY_URL
                // return response()->json(['status'=>'200','message' => 'DPO payment logged successfully'],200);
            } else {
                return redirect(env('TestPay_URL').'reinstate_error');
                // return response()->json(['status' => '401','message' => 'Policy is not renewed. Please try again'], 401);
            }

        } elseif ( $request->paymentMethod == 'Realpay' || $request->payment_method == 'RealPay' && $request->leadSource = 'pay.alphadirect.co.bw') {
            if ($policyDetails->save() && isset($new_term_id)) {
                return response()->json(['status' => 200, 'message' => 'Policy reinstate succesfully.', 'policy_number' => $policyDetails->policyNumber ], 200);
            } else {
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
                return response()->json(['status' => '401','message' => 'Policy is not reinstate. Please try again'], 401);
            }
        } else{
            if ($policyDetails->save() && isset($new_term_id)) {
                return response()->json(['status' => 200, 'message' => 'Policy reinstate succesfully.', 'policy_number' => $policyDetails->policyNumber ], 200);
            } else {
                //response for unsuccessful deletetion - (Important - status code for Flutter app)
                return response()->json(['status' => '401','message' => 'Policy is not reinstate. Please try again'], 401);
            }
        }

        }catch(Exception $e){
            return response()->json(['status' => '401','message' => 'Policy is not reinstate. Please try again'], 401);
        }

    }
    public function urlPaymentForm(Request $request){
        try{
            // dd($request->all());
        }catch (\Exception $ex){

        }
    }

    public function cashbackEvent()
    {
        event(new \AlphaDirect\Events\CustomerCashbackEvent('MIS2021003802'));
    }
    public function preinspectionsEvent()
    {
       $policy = Policy::join('customer','customer.id','=','policies.customer_id')
       ->join('customer_kyc','customer_kyc.customer_id','=','customer.id')
       ->where('policies.status',1)
       ->where('policies.product_id',3)
       ->where('policies.preinspection','!=',1)
       ->where('customer_kyc.compliance',1)->get(
        [
            'policies.id',
            'policies.customer_id',
            'policies.policyNumber',
            'policies.policyActivatedDate',
            'customer.firstName',
            'customer.lastName',
            'customer.cellphone',
            'customer.email'
        ]
       );
       $policy1 =  $policy->count();
       $mytime = Carbon::now()->format('Y-m-d');

       if(!Preinspections::where('created_at', 'like', '%' . $mytime . '%')->exists()){
       if($policy1 >= 1){
       foreach($policy as $p){
             $preinspection = new Preinspections();

             $preinspection->policy_id = $p->id;
             $preinspection->policy_number = $p->policyNumber;
             $preinspection->customer_id = $p->customer_id;
             $preinspection->first_name = $p->firstName;
             $preinspection->last_name = $p->lastName;
             $preinspection->cellphone = $p->cellphone;
             $preinspection->email = $p->email;
             $preinspection->policy_activated_date = $p->policyActivatedDate;
             $preinspection->save();

       }
    }
    $mytime4 = Carbon::now()->format('Y-m-d');
    $policy4 = Preinspections::where('created_at', 'like', '%' . $mytime4 . '%')->get();
    $policy5 =  $policy4->count();
       return response()->json(['status' => 'Created = '. $policy5, 'status2' => $policy4]);
    }else{
    $mytime1 = Carbon::now()->format('Y-m-d');
    $policy2 = Preinspections::where('created_at', 'like', '%' . $mytime1 . '%')->get();
    $policy3 =  $policy2->count();
    return response()->json(['status' => 'Already Created = ' . $policy3, 'status2' => $policy2]);
    }
   }

   public function checkPolicy(Request $request)
   {
       $request->validate([
           'policyNumber' => 'required'
       ]);

       if(Policy::where('policyNumber', $request->policyNumber)->exists())
       {
            $customer_id = Policy::where('policyNumber', $request->policyNumber)->pluck('customer_id');
            $customer = Customer::where('id', $customer_id)->first(['firstName', 'lastName', 'email', 'cellphone']);
            return response()->json(['status' => true, 'message' => 'Policy number exists', 'customer' => $customer], 200);
       }else{
            return response()->json(['status' => false, 'message' => 'Policy number does not exists'], 401 );
       }
   }


   public function createSchPayForOrange(Request $request)
    {
        $request->validate([
            'policyNumber' =>'required'
        ]);

        try{
            $policy = Policy::with('customer')->where('policyNumber', $request->policyNumber)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            if($policy->customer->email != null)
            {
                ScheduleTransactionEvent::dispatch($policy);

            }else{
                return response()->json(['status' => true , 'message' => 'Please provide email.'], 200);
            }

            return response()->json(['status' => true , 'message' => 'Policy schedules transaction created successfully.'], 200);
        }catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function logPaymentReinstate(Request $request){
        try{
            $policy = Policy::where('id',$request->get('policy_id'))->first();
            if(isset($request->email)){
                $customer = Customer::where('id',$policy->customer_id)->first();
                if (isset($customer)) {
                    $customer->email = $request->email;
                    $customer->save();
                }
            }

            if ($request->reinstate_type == 'reinstate_fresh') {
                $discSurData   = PolicyDiscountSurcharge::where('policy_id',$policy->id)->orderBy('id','desc')->first('new_value');
                $premium       = MotorComprehensiveQuotes::where('quoteNumber',$policy->quoteNumber)->orderBy('id','desc')->first();
                $total_premium = null;
                if (isset($discSurData)) {

                    $total_premium = $discSurData->new_value;

                    if ($request->frequency == 1) {
                        $policyCon = new PolicyController();
                        $premium = $policyCon->getMonthlyPrem(3,$total_premium);
                        $premium = round($premium,2);
                    } elseif ($request->frequency == 2) {
                        $premium = $total_premium / 3;
                        $premium = round($premium,2);
                    } elseif ($request->frequency == 3) {
                        $premium = $total_premium;
                    } else {
                        $premium = null;
                    }

                } elseif (isset($premium)) {
                    $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
                    $total_premium = round($total_premium['annual'],2);
                    if ($request->frequency == 1) {
                        $premium = $premium->premiumMonthly;
                    } elseif ($request->frequency == 2) {
                        $premium = $premium->premium3Inst;
                    } elseif ($request->frequency == 3) {
                        $premium = $premium->premiumAnnually;
                    } else {
                        $premium = null;
                    }
                } elseif (isset($policy->premium)) {
                    $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
                    $total_premium = round($total_premium['annual'],2);
                    $premium = $policy->premium;
                } else {
                    $premium = null;
                }
            } else {
                $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
                $total_premium = round($total_premium['annual'],2);

                $policyCon = new PolicyController();
                $balance = $policyCon->getPolicyBalance($policy->id);
                if($balance->getStatusCode() == 200){
                    $premium = $balance->getData()->balance;
                    $premium = number_format(abs($premium), 2, '.', '');
                }else{
                    $premium = null;
                }
            }

            // if (isset($premium)) {
            //     // $premium = PolicyPremiumReratingLog::where('ratings_id',$quotesData->ratings_id)->first();
            //     if ($request->frequency == 1) {
            //         $premium = $premium->premiumMonthly;
            //     } elseif ($request->frequency == 2) {
            //         $premium = $premium->premium3Inst;
            //     } elseif ($request->frequency == 3) {
            //         $premium = $premium->premiumAnnually;
            //     } else {
            //         $premium = null;
            //     }
            // } elseif (isset($policy->premium)) {
            //     $premium = $policy->premium;
            // } else {
            //     $premium = null;
            // }

            if($policy){
                $banking = CustomerBanking::where('policy_id',$policy->id)->first();
                $cancel = $this->cancelPolicyPayment($policy->id,$banking->billing);

                if($banking == null)
                    $banking = new CustomerBanking();

                $banking->policy_id        = $policy->id;
                $banking->billing          = $request->payment_method;
                $banking->bankName         = $request->BankName;
                $banking->branchCode       = $request->BranchCode;
                $banking->accountType      = $request->accountType;
                $banking->accountNumber    = $request->accountNumber;
                $banking->billingStartDate = Carbon::now()->format('Y-m-d');
                $banking->billing_day      = Carbon::now()->format('d');
                $banking->save();

                // $policy->premium_freq = $request->frequency;
                // $policy->premium = $request->premium;
                // $policy->save();

                $newPaymentMethod = $request->get('payment_method');

                if($newPaymentMethod == 'RealPay'){
                    $log = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    // $addLog = $log->logEvent($policy->id, 1);
                    // $responseArr = array('ClientCreated' => 0, 'ContractCreated' => 0);
                    // $stringArr = \Opis\Closure\serialize($responseArr);

                    $transaction = new Transaction();
                    $transaction->policyNumber = $policy->policyNumber;
                    $transaction->amount = $policy->premium;
                    $transaction->customer_id = $policy->customer_id;
                    $transaction->realPayTransaction_id = $policy->id;
                    $transaction->referenceNumber = $policy->policyNumber;
                    $transaction->status = "PENDING";
                    $transaction->save();

                    $realpay = $this->storeRealPayPaymentForRenewal($request);
                    // $realpay = $log->logRealpayPaymentForPolicyRenewal($request);
                    // dd($realpay);
                    if ($realpay->getData()->status == 200) {
                        $request['policy_id'] = $request->policyID;
                        if (isset($request['instalment_status'])) {
                            $request['instalment_status'] = $realpay->getData()->instalmentStatus;
                        } else {
                            $request['instalment_status'] = NULL;
                        }

                        if (!isset($request->new_premium)) {
                            // $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
                            // $total_premium = round($total_premium['annual'],2);
                            $request['new_premium'] = $total_premium;
                        }

                        if (!isset($request->first_premium) || isset($request->first_premium)) {
                            $request['first_premium'] = $realpay->getData()->first_premium;
                        }

                        if (isset($realpay->getData()->first_premium)) {
                            $request['term_permium'] = $realpay->getData()->first_premium;
                            if ($request->reinstate_type == 'Reinstate_arrears') {
                                $request->term_permium = $policy->premium;
                            }
                        }

                        if (isset($realpay->getData()->calculated_premium)) {
                            $request['calculated_premium'] = $realpay->getData()->calculated_premium;
                        }

                        if (isset($premium)) {
                            $request['balance_due'] = $premium;
                        }
                        $reinstatePolicy = $this->PolicyReinstate($request);
                        // dd($renewpolicy);
                        if ($reinstatePolicy->getData()->status == 200) {
                            event(new \AlphaDirect\Events\policyLifecycle($request->policy_id , "Reinstate"));
                            return response()->json(['success'=>'true','message' => 'Realpay payment logged successfully'],200);
                        } else {
                            return response()->json(['success'=>'false','message' => 'Failed to log realpay payment'.$reinstatePolicy->getData()->message],200);
                        }

                    } else {
                        return response()->json(['success'=>'false','message' => 'Failed to logged Realpay payment'.$realpay->getData()->message],200);
                    }

                }   elseif ($newPaymentMethod == "DPO") {

                    // if ($renew_premium->getData()->status == 200) {
                        if (!isset($request->new_premium)) {
                            // $total_premium = $this->getMotorComprehensivePremium($policy->policyNumber);
                            // $total_premium = round($total_premium['annual'],2);
                            $request['new_premium'] = $total_premium;
                        }

                        if (!isset($request->first_premium)) {
                            $request['first_premium'] = $premium;
                            $request['term_permium'] = $premium;
                        }

                        // if (isset($realpay->getData()->first_premium)) {
                        //     $request['term_permium'] = $policy->premium;
                        // }

                        // if (isset($realpay->getData()->calculated_premium)) {
                        //     $request['calculated_premium'] = $realpay->getData()->calculated_premium;
                        // }


                        // dd($renew_amount,$request->premium);
                        $dpo                  = new DpoPaymentController;
                        $request->filter      = 'PolicyNumber';
                        $request->searchValue = $policy->policyNumber;
                        $request->leadSource  = 'pay.alphadirect.co.bw';
                        $request->amount      = $premium;
                        $request->requestType = 'reinstate';
                        $request->new_premium = $request->new_premium;
                        $request->reinstate_type = $request->reinstate_type;
                        $request->reinstated_by = $request->reinstated_by;
                        if ($request->reinstate_type == 'Reinstate_arrears') {
                            $request->premium = $policy->premium;
                            $request->term_permium = $policy->premium;
                            $request->balance_due = $premium;
                        }
                        $dpoReturn            = $dpo->findPolicyForOnlinePayment($request);
                        // dd($dpoReturn);
                        return $dpoReturn;
                    // } else{
                    //     return response()->json(['success'=>'false','message' => 'Something went wrong. Premium not found'],401);
                    // }
                }
                else{
                    return response()->json(['success'=>'true','message' => 'Payment method not found'],401);
                }

            }else{
                return response()->json(['success'=>'false','message' => 'Policy data not found'],401);
            }

        }catch(\Exception $ex){
            return response()->json(['success'=>'false','message' => $ex->getMessage()],401);
        }
    }

    public function PolicyReinstateFailed(Request $request)
    {
        /* $request->validate([
            'policyNumber' => 'required',
            'payment_method' => 'required'
        ]); */
        $policy_number = base64_decode($request->policy_number);
        $amount        = base64_decode($request->amount);
        /* print_r($policy_number. '<br>');
        print_r($amount);
        dd($request->all()); */
        DB::beginTransaction();
        try{
            $policy = Policy::where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)      ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                ];


            //fire event send sms and email when policy is created
            $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = (float)str_replace(',','',$amount) ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->frequency) ?  $request->frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->save();

            DB::commit();

                $notification = array(
                    'message'      => 'Payment failed, please try again later',
                    'amount'       => $amount,
                    'alert-type'   => 'failed',
                    'policyNumber' => $policy_number,
                    'amount'       => $amount
                );

                $sms = new PaymentTransaction();
                $sent = $sms->sendSMSOnFailedPayments($policy_number,$amount);
                return redirect(env('START_URL').'payment_failed.php')->with('notification', $notification);


        }catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function PolicyReinstateDeclined(Request $request)
    {
        /* $request->validate([
            'policyNumber' => 'required',
            'payment_method' => 'required'
        ]); */
        $policy_number = base64_decode($request->policy_number);
        $amount        = base64_decode($request->amount);
        /* print_r($policy_number. '<br>');
        print_r($amount);
        dd($request->all()); */
        DB::beginTransaction();
        try{
            $policy = Policy::where('policyNumber', $policy_number)->first();

            if($policy == null)
            {
                 return response()->json(['status' => false , 'message' => 'Policy not found'], 401);
            }

            if($policy->status == 2)
            {
                return response()->json(['status' => false , 'message' => 'Canceled policy is not available for this feature.'], 401);
            }

            $data = [
                    "token"            => $request->TransactionToken,
                    "policy_number"    => $policy->policyNumber,
                    "TransID"          => isset($request->TransactionToken)  ? strtoupper($request->TransactionToken)     : null,
                    "CCDapproval"      => isset($request->CCDapproval)  ? strtoupper($request->CCDapproval) : null,
                    "PnrID"            => isset($request->PnrID)        ? strtoupper($request->PnrID)       : null,
                    "TransactionToken" => isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null,
                    "CompanyRef"       => isset($request->CompanyRef)   ? strtoupper($request->CompanyRef)  : null,
                    "customer_id"      => $policy->customer_id,
                ];


            //fire event send sms and email when policy is created
            /* $event                                       = VerifyTokenEvent::dispatch($data);
            $event                                       = $event[0];
            $paymentTransaction                          = new PaymentTransaction();
            $paymentTransaction->policyNumber            = $policy->policyNumber;
            $paymentTransaction->referenceNumber         = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->amount                  = (float)str_replace(',','',$amount) ;
            $paymentTransaction->status                  = $event['status'] == 1 ? 'SUCCESS' : 'FAILED';
            $paymentTransaction->paymentDate             = $event['status'] == 1 ? Carbon::now() :  Carbon::now();
            $paymentTransaction->paymentMethod           = 'DPO';
            $paymentTransaction->numberOfInstalmentsPaid = 0;
            $paymentTransaction->paymentFrequency        = isset($request->frequency) ?  $request->frequency : 1;
            $paymentTransaction->TransID                 = isset($request->TransactionToken)        ? strtoupper($request->TransactionToken)     : null;
            $paymentTransaction->CCDapproval             = isset($request->CCDapproval)    ? strtoupper($request->CCDapproval) : null;
            $paymentTransaction->PnrID                   = isset($request->PnrID)          ? strtoupper($request->PnrID)       : null;
            $paymentTransaction->TransactionToken        = isset($request->TransactionToken) ? strtoupper($request->TransactionToken) : null;
            $paymentTransaction->CompanyRef              = isset($request->CompanyRef)     ? strtoupper($request->CompanyRef)  : null;
            $paymentTransaction->save(); */

            DB::commit();

                return response()->json([
                    'message'      => 'Payment is canceled.',
                    'alert-type'   => 'failed',
                    'amount'       => $amount,
                    'policyNumber' => $policy_number,
                    'amount'       => $amount
                ]);

        }catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function updateBillingDateScheduleTransaction(Request $request){
        // dd($request->all());

        if (!auth::user()->hasPermissionTo('policy-update_billng_date_schedule_transaction'))
        {
            return redirect()->back()->withError('Sorry! You do not have permission to access this page!');
        }

        $request->validate([
            'billing_day'   => 'required|integer|min:1|max:28',
            'policy_number' => 'required',
            'product_id'    => 'required|integer|min:1',
            'premium_freq'  => 'required|',
        ]);
        if(!ScheduleTransaction::whereIn('status', [0, 1, 2, 3])->where('policy_number', $request->policy_number)->count() > 0 )
        {
            return redirect()->back()->withError('No records found.');
        }

        DB::beginTransaction();
        try{
            $scheduleTrans = ScheduleTransaction::whereIn('status', [0, 1, 2, 3])
                                                ->where('policy_number', $request->policy_number)
                                                ->orderBy('installment')
                                                ->get(['id', 'billing_date','added_by', 'installment']);

            $billing_date = $this->setDate($request->billing_day);
            // dd($billing_date);
            foreach($scheduleTrans as $k => $scheduleTran)
            {
                if($request->product_id == 3 && $request->premium_freq == 1 && $scheduleTran->installment == 1 )
                {
                    $scheduleTran->billing_date  = $k == 0 ? $billing_date : Carbon::parse($billing_date)->addMonthsNoOverflow($k)->format('Y-m-d');
                }else{
                    $scheduleTran->billing_date  = Carbon::parse($billing_date)->addMonthsNoOverflow($k)->format('Y-m-d');
                    // dd($scheduleTran->billing_date);
                }
                $scheduleTran->added_by  = auth()->user()->id;
                $scheduleTran->save();
            }

            DB::commit();
            return redirect()->back()->withSuccess('Billing date for schedule transactions updated successfully.');
        }catch(Exception $ex)
        {
            DB::rollback();
            return redirect()->back()->withError($ex->getMessage());
        }
    }

    public function updatePremiumScheduleTransaction(Request $request){
        // dd($request->all());

        if (!auth::user()->hasPermissionTo('policy-update_billng_date_schedule_transaction'))
        {
            return redirect()->back()->withError('Sorry! You do not have permission to access this page!');
        }

        $request->validate([
            'premium'   => 'required',
            'policy_number' => 'required',
            'product_id'    => 'required|integer|min:1',
            'premium_freq'  => 'required|',
        ]);
        if(!ScheduleTransaction::whereIn('status', [0, 1, 2, 3])->where('policy_number', $request->policy_number)->count() > 0 )
        {
            return redirect()->back()->withError('No records found.');
        }

        DB::beginTransaction();
        try{
            if($request->product_id == 3 && $request->premium_freq == 1)
            {
                $scheduleTrans = ScheduleTransaction::whereIn('status', [0, 1, 2, 3])
                                                ->where('policy_number', $request->policy_number)
                                                ->update([
                                                            'premium' => $request->premium,
                                                            'added_by' => auth()->user()->id
                                                        ]);
            }
            DB::commit();
            return redirect()->back()->withSuccess('Primium for schedule transactions updated successfully.');
        }catch(Exception $ex)
        {
            DB::rollback();
            return redirect()->back()->withError($ex->getMessage());
        }
    }

    public function setDate($day)
        {
            $current_timestamp = Carbon::now()->timestamp;
            $newDate           = date("d", $current_timestamp);
            $mnth              = (int)date("m", $current_timestamp);
            $yr                = (int)date("Y", $current_timestamp);

            $v    = cal_days_in_month(CAL_GREGORIAN, $mnth, $yr);
            $days = $newDate - $day;
            // dd($days);
            if ($days < 0 ) {
                // $date = Carbon::now()->addDays(abs($days))->format('Y-m-d');
                $date = Carbon::create($yr, $mnth, $day)->format('Y-m-d');
                return $date;
            } elseif ($days > 0) {
                $date = Carbon::create($yr, $mnth, $day)->addMonth()->format('Y-m-d');
                // $date = Carbon::now()->addDays($v - abs($days))->format('Y-m-d');  /*env('AVERAGE_DAYS')*/
                return $date;
            } else { //done
                $date = Carbon::create($yr, $mnth, $day)->addMonth()->format('Y-m-d');
                // $date = Carbon::create($yr, $mnth, $request->day)/* ->addDays($v - abs($days)) */->format('Y-m-d');  /*env('AVERAGE_DAYS')*/
                return $date;
            }
        }

    public function testAgentReport()
    {
        $agents=User::where('agency_id','!=',null)->where('active', 1)->limit(50)->get(array('id','firstName', 'lastName'));
          $agentData = [];
          $attachments = [];
          $complete_agentData=[];
          if($agents != null)
          {
                foreach($agents as $agent)
                {
                    // if($agent->id == 640){
                    /***** Monthly  *****/
                    $name=$agent->firstName.' '.$agent->lastName;
                    $policies=Policy::where('agent_id', $agent->id)->whereMonth('created_at',3)->count();
                    // $premium=Policy::where('agent_id',$agent->id)->whereMonth('created_at', Carbon::now()->month)->sum('premium');

                    $get_policy_id=Policy::where('agent_id',$agent->id)->where('status',1)->whereMonth('created_at',3)->get('id');
                    $premium=PolicyLedgers::whereIn('policy_id',$get_policy_id)->where('trans_type','Invoice')->whereMonth('accounting_date',3)->sum('debit');
                    // dd($premium);
                    $policies_number=Policy::where('agent_id',$agent->id)->whereMonth('created_at',3)->get('policyNumber');

                    $policies_cancelled = Policy::where('agent_id', $agent->id)->whereMonth('created_at', 3)->where('status',2)->count();
                    // $sum=PaymentTransaction::whereIn('policyNumber',$policies_number)->where('amount', '!=', 1)->where('status', 'Success')->where('is_refund',0)->whereMonth('created_at', Carbon::now()->month)->sum('amount');
                    $sum=PolicyLedgers::whereIn('policy_id',$get_policy_id)->where('trans_type','Payment')->whereMonth('accounting_date', 3)->sum('credit');

                    if($premium != 0 and $sum !=0)
                     {
                        $percentage=$sum/$premium*100;
                        $percentage=round($percentage, 2);
                     }
                     else{
                         $percentage=0;
                     }

                    array_push($agentData,['name'=>$name ,'policies'=>$policies,'premium'=>$premium,'sum'=>$sum,'percentage'=>$percentage,'policies_cancelled'=>$policies_cancelled]);


                    /***** Since Begining *****/

                    // $agentname=$agent->firstName.' '.$agent->lastName;
                    // $agentpolicies=Policy::where('agent_id', $agent->id)->count();
                    // $agent_policies_id=Policy::where('agent_id', $agent->id)->where('status',1)->get('id');
                    // // $agentpremium=Policy::where('agent_id',$agent->id)->sum('premium');
                    // $agentpremium=PolicyLedgers::whereIn('policy_id',$agent_policies_id)->where('trans_type','Invoice')->sum('debit');
                    // $agentpolicies_number=Policy::where('agent_id',$agent->id)->get('policyNumber');
                    // $agentCancelledpolicies=Policy::where('agent_id', $agent->id)->where('status',2)->count();

                    // // $agentsum=PaymentTransaction::whereIn('policyNumber',$policies_number)->where('amount', '!=', 1)->where('status', 'Success')->where('is_refund',0)->sum('amount');
                    // $agentsum=PolicyLedgers::whereIn('policy_id',$agent_policies_id)->where('trans_type','Payment')->sum('credit');

                    // if($agentpremium != 0 && $agentsum != 0)
                    //  {
                    //     $complete_percentage=$agentsum/$agentpremium*100;
                    //     $complete_percentage=round($complete_percentage, 2);
                    //  }else{
                    //     $complete_percentage=0;
                    //  }

                    // array_push($complete_agentData,['name'=>$agentname ,'policies'=>$agentpolicies,'premium'=>$agentpremium,'sum'=>$agentsum,'percentage'=>$complete_percentage,'agentCancelledpolicies'=>$agentCancelledpolicies]);
                }

                $agentData = collect($agentData)->sortByDesc('percentage')->all();
                // dd($agentData);
                return view('admin.policy.testAgentReportView',compact('agentData'));

            }
    }

    public function createRealpayPaymentView(Request $request)
    {
        try {
            return view('admin.policy.createRealPayPayment');
        } catch (\Exception $ex) {
            return redirect()->back()->withError($ex->getMessage());
        }
    }


    public function generatePolicySmsEmailLogPdf($id)
    {
        try{
            $policy = Policy::where('id',$id)->first('policyNumber');
            $smsEmailLogs = SMSEmailLogs::where('policyNumber',$policy->policyNumber)->get(array('id','policyNumber','type','message_id','message','content','hook','attachments','to_email','to_cellphone','status','created_at'));

            $data = [
                'smsEmailLogs' => $smsEmailLogs
            ];

            libxml_use_internal_errors(true);
            $date = Carbon::now()->timestamp;
            $path = 'SmsEmailLogs/'. $date.'/'.$policy->policyNumber .'smsemail.pdf';
           // return view('admin.policy.smsEmailLogs',$data);
            $pdf = PDF::loadView('admin.policy.smsEmailLogs', $data);
            Storage::disk('s3')->put($path, $pdf->output(), 'public');

            return Storage::disk('s3')->download($path);

        }catch(\Exception $ex){
            return redirect()->back()->withError($ex->getMessage().$ex->getLine());
        }
    }
    public function dpoPaymentRecord(Request $request){
      $data =   PaymentActivityLog::where('policy_number',$request->id)
               ->get(['id','policy_number','amount','api_name','request_json','response_json','CompanyAccRef','TransToken','reason','created_at']);


    return DataTables::of($data)->make(true);


    }


    public function updateExpiredPolicyStatus($policy_id)
    {
        try {
            $policy = Policy::where('id',$policy_id)->first();
            if (isset($policy)) {
                $policy->status = 3;
                $policy->save();

                return response()->json(['status' => true, 'message' => 'Policy updated successfully', 'type' => 'success'], 200);
            }

            return response()->json(['status' => false, 'message' => 'Policy not found', 'type' => 'error'], 401);

        } catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }


    public function cancelContractForExpiredPolicy($policy)
    {
        try {
            $banking = CustomerBanking::where('policy_id', $policy->id)->first();
            if (isset($banking)) {
                if ($banking->billing == 'VCS') {
                    $transctionsRow = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'desc')->first();

                    if($transctionsRow && $transctionsRow->referenceNumber){
                        $referenceNumber = $transctionsRow->referenceNumber;
                        $vcs = new PaymentController;
                        $vcs->suspendTransactionOnVCS($referenceNumber);
                    }
                } elseif ($banking->billing == 'RealPay' || $banking->billing == 'Realpay') {

                    $clientNumber = $this->cancelRealpayContract($policy->id);

                    if($clientNumber != null){
                        $can = RealpayCancelRequests::where('policy_id',$policy->id)->first();
                        if (isset($can)) {
                            $can->cancel_status = 1;
                            $can->save();
                        }

                        $trans = Transaction::where('realPayTransaction_id',$policy->id)
                            ->orderBy('id', 'DESC')
                            ->first();
                        if($trans != null){
                            $trans->status = "CANCELLED";
                            $trans->save();
                        }
                    }else{
                        $can = RealpayCancelRequests::where('policy_id',$policy->id)->first();
                        if (isset($can)) {
                            $can->cancel_status = 2;
                            $can->save();
                        }
                    }

                } elseif ($banking->billing == 'DPO') {
                    $dpoCon = new DpoPaymentController();
                    $cancelContract = $dpoCon->CancelContractForDpoPolicy($policy);
                }

                return response()->json(['status' => true, 'message' => 'Contract cancelled successfully', 'type' => 'success'], 200);
            }

            return response()->json(['status' => false, 'message' => 'Payment method not found', 'type' => 'error'], 401);

        } catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function sendSmsEmailCustomerExpiredPolicy($policy)
    {
        try {
            $customer = Customer::where('id',$policy->customer_id)->first();
            if (isset($customer)) {
                if($customer->cellphone != null){
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailCustomerPolicyExpired($customer->cellphone,$customer->firstName,$policy->policyNumber);
                }

                activity('Policy Cancel SMS')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('SMS send');

                if ($customer->email != null) {
                    $data              = new \stdClass();
                    $data->user_id     = null;
                    $data->policy_id   = $policy->id;
                    $data->customer_id = $customer->id;
                    $data->hook        = 'customer_expired_policy';
                    $data->attachment  = null;
                    $emailTemplate     = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown          = new MailTemplate($data);
                    $html              = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                    event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,null,['policyNumber' => $policy->policyNumber,'hook' => $data->hook]));
                }

                activity('Policy Cancel Email')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Email send');


                return response()->json(['status' => true, 'message' => 'SMS and Email sent successfully', 'type' => 'success'], 200);
            }

            return response()->json(['status' => false, 'message' => 'Sms and Email not sent', 'type' => 'error'], 401);

        } catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }


    public function convertPolicyFrequency(Request $request)
    {
        try {
            $policy = Policy::where('id',$request->policyID)->first();
            $new_premium = null;
            if (isset($policy)) {
                if (isset($policy->premium) && isset($request->frequency) && $policy->status != 2) {
                    $kyc = KYC::where('customer_id', $policy->customer_id)->first();
                    $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                    ->where('status','!=','CANCELLED')
                    ->where('policyNumber','!=','FAILED')
                    ->orderBy('id', 'DESC')->get();
                    if ($transaction == null){
                        $transaction = Transaction::where('policyNumber', $policy->policyNumber)
                        ->where('status','!=','CANCELLED')
                        ->where('policyNumber','!=','FAILED')
                        ->orderBy('id', 'DESC')->get();
                    }

                    if ($policy->status == 1 && isset($transaction) && $policy->premium_freq == 3) {
                        return response()->json(['status' => false, 'message' => 'Policy is not available for changing payment frequency', 'type' => 'error'], 401);
                    }

                    if ($policy->status != 1 && !isset($transaction) && $policy->premium_freq == 3) {
                            # code... 3 inst and monthly
                            if ($policy->product_id == 3 && isset($policy->premium)) {
                                $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                                // dd($motorPreminum);
                                if (isset($motorPreminum) && isset($request->frequency) && $request->frequency != 3) {
                                    $premium = $this->getMotorCompPremiumForConvertingFreq($motorPreminum['annual'],$request->frequency);
                                    $new_premium = $premium['annualPremium'];
                                    if ($request->frequency == 1) {
                                        $new_premium = $premium['monthlyPremium'];
                                    } elseif ($request->frequency == 2) {
                                        $new_premium = $premium['threeInstlPremium'];
                                    } elseif ($request->frequency == 3) {
                                        $new_premium = $premium['annualPremium'];
                                    }
                                }
                            }
                    } elseif ($policy->status == 1 && isset($transaction) && $policy->premium_freq == 2) {
                        # code... annual and monthly provided only 1 or two installment has been made

                        if ($policy->product_id == 3 && isset($policy->premium)) {
                            $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                            if (isset($motorPreminum) && isset($request->frequency) && $request->frequency != 2) {
                                $countTx = $transaction->count();
                                if ($countTx < 3) {
                                    $totalAmtPaid = $transaction->sum('amount');
                                    $balanceAmt = $motorPreminum['annual'] - $totalAmtPaid;
                                    $premium = $this->getMotorCompPremiumForConvertingFreq($balanceAmt,$request->frequency);
                                    $new_premium = $premium['threeInstlPremium'];
                                    if ($request->frequency == 1) {
                                        $new_premium = $premium['monthlyPremium'];
                                    } elseif ($request->frequency == 2) {
                                        $new_premium = $premium['threeInstlPremium'];
                                    } elseif ($request->frequency == 3) {
                                        $new_premium = $premium['annualPremium'];
                                    }
                                }
                            }
                        }
                    } elseif ($policy->premium_freq == 1) {
                        if ($policy->product_id == 3 && isset($policy->premium)) {
                            $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                            if (isset($motorPreminum) && isset($request->frequency)) {
                                $totalAmtPaid = 0;
                                if (isset($transaction)) {
                                    $totalAmtPaid = $transaction->sum('amount');
                                }

                                $balanceAmt = $motorPreminum['annual'] - $totalAmtPaid;
                                $premium = $this->getMotorCompPremiumForConvertingFreq($balanceAmt,$request->frequency);
                                if ($request->frequency == 1) {
                                    $new_premium = $premium['monthlyPremium'];
                                } elseif ($request->frequency == 2) {
                                    $new_premium = $premium['threeInstlPremium'];
                                } elseif ($request->frequency == 3) {
                                    $new_premium = $premium['annualPremium'];
                                }
                            }
                        }
                    } elseif ($policy->status == 0) {
                        $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                        if (isset($motorPreminum)) {
                            if ($request->frequency == 1) {
                                $new_premium = round($motorPreminum['monthly'],2);
                            } elseif ($request->frequency == 2) {
                                $new_premium = round($motorPreminum['3_inst'],2);
                            } elseif ($request->frequency == 3) {
                                $new_premium = round($motorPreminum['annual'],2);
                            }

                        }
                    } else {
                        return response()->json(['status' => false, 'message' => 'Policy is not available for changing payment frequency', 'type' => 'error'], 401);
                        // return redirect()->back()->withError('Policy is not available for changing payment frequency');
                    }

                    if (isset($new_premium) && $request->frequency) {

                        $addPremiumLog = new PolicyPremiumLogs();
                        $addPremiumLog->policy_id = $policy->id;
                        $addPremiumLog->policyNumber = $policy->policyNumber;
                        $addPremiumLog->old_premium = $policy->premium;
                        $addPremiumLog->old_first_premium = $policy->first_premium;
                        $addPremiumLog->old_first_premium_wvat = $policy->first_premium_wvat;
                        $addPremiumLog->old_premium_freq = $policy->premium_freq;
                        $addPremiumLog->old_billingStartDate = $policy->billingStartDate;

                        $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
                        if (isset($motorPreminum)) {
                            $addPremiumLog->old_annual_premium = round($motorPreminum['annual'],2);
                            $policy->annual_premium = round($motorPreminum['annual'],2);
                        } else {
                            $addPremiumLog->old_annual_premium = null;
                        }

                        $policy->premium = $new_premium;
                        $policy->first_premium = $new_premium;
                        $policy->first_premium_wvat = $new_premium;
                        $policy->premium_freq = $request->frequency;
                        $policy->billingStartDate = Carbon::now()->format("Y-m-d");
                        $policy->save();

                        $addPremiumLog->new_premium = $new_premium;
                        $addPremiumLog->new_first_premium = $new_premium;
                        $addPremiumLog->new_first_premium_wvat = $new_premium;
                        $addPremiumLog->new_premium_freq = $request->frequency;
                        $addPremiumLog->new_billingStartDate = Carbon::now()->format("Y-m-d");
                        $addPremiumLog->action_performed_by = auth()->user()->id;

                        $getNewPolicyData = Policy::where('id',$request->policyID)->first();
                        $newMotorPreminum = $this->getMotorComprehensivePremium($getNewPolicyData->policyNumber);
                        if (isset($newMotorPreminum)) {
                            $addPremiumLog->new_annual_premium = round($newMotorPreminum['annual'],2);
                        } else {
                            $addPremiumLog->new_annual_premium = null;
                        }

                        $addPremiumLog->save();

                        $fetchPolicy = Policy::with('customer')->where('id',$request->policyID)->first();

                        $banking = CustomerBanking::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first();
                        if (isset($banking) && isset($banking->billing)) {
                            if ($banking->billing == 'RealPay' || $banking->billing == 'Realpay') {
                                $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                                $request['policyID'] = $policy->id;
                                $request['BankName'] = $banking->bankName;
                                $request['BranchCode'] = $banking->branchCode;
                                $request['accountType'] = $banking->accountType;
                                $request['accountNumber'] = $banking->accountNumber;
                                $request['request_type'] = 'Convert Policy Frequency';
                                $request['newConvertedPremium'] = $new_premium;
                                $createContract = $realpay->logRealpayPaymentForPolicyRenewal($request);

                                if ($createContract->getData()->status != 200) {
                                    return response()->json(['status' => false, 'message' => $createContract->getData()->meszsage, 'type' => 'error'], 401);
                                    // return redirect()->back()->withError($createContract->getData()->message);
                                }
                            } elseif ($banking->billing == 'DPO') {
                                if ($policy->status != 0) {
                                    $scheduleTran = ScheduleTransaction::where('policy_id', $policy->id)->get();
                                    if (isset($scheduleTran)) {
                                        $dpoCon = new DpoPaymentController();
                                        $cancelContract = $dpoCon->CancelContractForDpoPolicy($fetchPolicy);
                                        ScheduleTransactionEvent::dispatch($fetchPolicy);
                                    } else {
                                        return response()->json(['status' => false, 'message' => 'Scheduled transaction not found', 'type' => 'error'], 401);
                                        // return redirect()->back()->withError('Scheduled transaction not found');
                                    }
                                }
                            } //elseif ($banking->billing == 'VCS') {
                                # code...
                            // }
                        } else {
                            return response()->json(['status' => false, 'message' => 'Customer banking details not found', 'type' => 'error'], 401);
                            // return redirect()->back()->withError('Customer banking details not found');
                        }
                    }
                }

                activity('Policy')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Policy premium and frequency updated');

                return response()->json(['status' => true, 'message' => 'Payment frequency updated successfully', 'type' => 'success'], 200);
                // return redirect()->back()->withSuccess('Payment frequency updated successfully.');
            } else {
                return response()->json(['status' => false, 'message' => 'Policy not found', 'type' => 'error'], 401);
                // return redirect()->back()->withError('Policy not found');
            }
        } catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage().$ex->getLine(), 'type' => 'error'], 401);
            // return redirect()->back()->withError($ex->getMessage().$ex->getLine());
        }
    }


    public function getPremiumLogsDataDetails($id)
    {
        $premiumLogs = PolicyPremiumLogs::where('policyNumber',$id)->orderBy('id','desc')->get();
        return DataTables::of($premiumLogs)

            ->editColumn('action_performed_by', function ($premiumLogs) {
                $action_performed_by = 'N/A';
                if (isset($premiumLogs->action_performed_by)) {
                    $user = User::where('id',$premiumLogs->action_performed_by)->first();
                    $action_performed_by = $user->firstName.' '.$user->lastName;
                }

                return $action_performed_by;
            })

            ->make(true);
    }


    public function CancelPaymentsForPolicy($policy)
    {
        try {
            // $banking = CustomerBanking::where('policy_id', $policy->id)->first();
            // $isCancel = false;
            // if (isset($banking)) {
            //     switch ($banking->billing) {
            //         case 'VCS':
            //             $addLog = new CancelVCSTransactionLog();
            //             $addLog->policyNumber = $policy->policyNumber;
            //             $addLog->cancellation_date = Carbon::now()->format('Y-m-d');
            //             $addLog->save();

            //             $isCancel = true;
            //             break;
            //         case 'DPO':
            //             $dpoCon = new DpoPaymentController();
            //             $cancelContract = $dpoCon->CancelContractForDpoPolicy($policy);
            //             if ($cancelContract->getData()->status == true) {
            //                 $isCancel = true;
            //             }
            //             break;
            //         case 'N-Genius':
            //             if (NgeniusTransection::where('policy_number',$policy->policyNumber)->orderby('id','desc')->where('status',1)->exists()) {
            //                 $policyNumber = $policy->policyNumber;
            //                 $ngenius = new NgeniusPaymentController();
            //                 $cancel =  $ngenius->NgeniusRecurringDeletedata2($policyNumber);
            //                 if ($cancel == 1) {
            //                     $isCancel = true;
            //                 }
            //             }
            //             break;
                    // case 'RealPay':
                        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

                        if ($policy->product_id == 3) {
                            $clientNumber = $realpay->cancelRealpayContract($policy->id);
                        } else {
                            $clientNumber = $realpay->cancelRealpayContractsForInstProduct($policy->id);
                        }

                        if($clientNumber != null){
                            $can = RealpayCancelRequests::where('policy_id',$policy->id)->first();
                            if (isset($can)) {
                                $can->cancel_status = 1;
                                $can->save();
                            }

                            $trans = Transaction::where('realPayTransaction_id',$policy->id)
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($trans != null){
                                $trans->status = "CANCELLED";
                                $trans->save();
                            }

                            $isCancel = true;
                        }else{
                            $can = RealpayCancelRequests::where('policy_id',$policy->id)->first();
                            if (isset($can)) {
                                $can->cancel_status = 2;
                                $can->save();
                            }

                            $isCancel = false;
                        }

                //         break;
                //     default:
                //         $isCancel = false;
                // }

                $updatePolicy = Policy::where('id',$policy->id)->first();
                if (isset($updatePolicy) && $isCancel == true) {
                    $updatePolicy->isPaymentCancel = 1;
                    $updatePolicy->save();
                    return response()->json(['status' => true, 'message' => 'Contract cancelled successfully', 'type' => 'success'], 200);
                } else {
                    return response()->json(['status' => false, 'message' => 'Failed to cancel payment', 'type' => 'error'], 401);
                }

            // } else {
            //     return response()->json(['status' => false, 'message' => 'Banking details not found', 'type' => 'error'], 401);
            // }
        } catch (\Exception $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage(), 'type' => 'error'], 500);
        }
    }

    public function addTermsToMotorCompPoliciesIfNotExists($policyNumber)
    {
        // try {
            $getPolicies = Policy::where('policyNumber',$policyNumber)->first();
            if (isset($getPolicies)) {
                $checkTerm = PolicyTerm::where('policy_id',$getPolicies->id)->count();

                $payTrx = PaymentTxArchive::where('policyNumber', $policyNumber)->whereYear('paymentDate','>=',2020)->whereIn('status',['Success','SUCCESS','success','1'])->first();
                if (!isset($payTrx)) {
                    $payTrx = PaymentTransaction::where('policyNumber', $policyNumber)->whereYear('paymentDate','>=',2020)->whereIn('status',['Success','SUCCESS','success','1'])->first();
                }

                if (isset($payTrx)) {
                    if(str_contains($payTrx->paymentDate, '/')){
                        $payTrx->paymentDate = Carbon::createFromFormat('d/m/Y', $payTrx->paymentDate)->format('Y-m-d');
                    }

                    $policyExpiryDate = Carbon::parse($payTrx->paymentDate)->addYear(1)->format('Y-m-d');
                    // dd($payTrx->paymentDate,$policyExpiryDate);
                    // if (isset($policyExpiryDate) && Carbon::parse($policyExpiryDate)->lt(Carbon::now())) {
                    //     $start_date  = Carbon::now()->format('Y-m-d');
                    //     $expiry_date = Carbon::now()->addYear()->subDays(1)->format('Y-m-d');

                    // } else {
                        // $start_date  = Carbon::createFromFormat('Y-m-d', $policyExpiryDate)->addDays(1)->format('Y-m-d');
                        // $expiry_date = Carbon::createFromFormat('Y-m-d', $start_date)->addYear()->subDays(1)->format('Y-m-d');
                    // }

                    $status = 'Deactive';

                    $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
                    $oldTermPaymentMethod = $realpay->checkPaymentMethod($getPolicies->policy_id);

                    $policyPremium = $this->getMotorComprehensivePremium($policyNumber);
                    $total_premium = round($policyPremium['annual'],2);

                    $term_count = PolicyTerm::where('policy_id',$getPolicies->id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->count();

                    if ($checkTerm == 0) {
                        $status = 'Deactive';

                        if (isset($policyExpiryDate)) {
                            if ($policyExpiryDate < Carbon::now()) {
                                $status = 'Deactive';
                            } else {
                                $status = 'Active';
                            }
                        }

                        $data = [
                            'policy_id' => $getPolicies->id,
                            'term_start_date' => ($payTrx->paymentDate) ? $payTrx->paymentDate : $getPolicies->created_at,
                            'term_end_date' => $policyExpiryDate,
                            'premium' => $getPolicies->premium,
                            'annual_premium' => $total_premium,
                            'vat' => $getPolicies->vat,
                            'vat_percent' => $getPolicies->vat_percent,
                            'renewed_by' => NULL,
                            'renewals_date' => $policyExpiryDate,
                            'frequency' => $getPolicies->premium_freq,
                            'first_premium' => $getPolicies->first_premium,
                            'billing_start_date' => $getPolicies->billingStartDate,
                            'policy_documents' => NULL,
                            'policyActivatedDate' => $payTrx->paymentDate,
                            'payment_method' => $oldTermPaymentMethod,
                            'payment_reference' => $getPolicies->id,
                            'trans_type' => 'NEW BUSINESS',
                            'status' => $status,
                            'created_at' => Carbon::now()->format('Y-m-d'),

                        ];
                        $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
                    }

                    $countTerms = PolicyTerm::where('policy_id',$getPolicies->id)->where('status','Active')->orderBy('id','desc')->count();

                    if ($countTerms == 0) {
                        $termsdiffInYear =  \Carbon\Carbon::createFromTimeStamp(strtotime($payTrx->paymentDate))->diffInYears();
                        // dd("tt",$termsdiffInYear);
                        $new_status = 'Deactive';
                        for ($i=0; $i < $termsdiffInYear; $i++) {
                            // $getTerms = PolicyTerm::where('policy_id',$getPolicies->id)->where('status','Deactive')->orderBy('id','desc')->first();

                            $start_date  = Carbon::createFromFormat('Y-m-d', $policyExpiryDate)->addYear($i)->addDays(1)->format('Y-m-d');
                            $expiry_date = Carbon::createFromFormat('Y-m-d', $start_date)->addYear()->format('Y-m-d');

                            if ($status == 'Deactive') {
                                if ($expiry_date > Carbon::now()->format('Y-m-d')) {
                                    $new_status = 'Active';
                                } else {
                                    $new_status = 'Deactive';
                                }
                            }

                            $todayD = Carbon::now()->format('Y-m-d');
                            if ($expiry_date <= $todayD) {
                                $newData = [
                                    'policy_id' => $getPolicies->id,
                                    'term_start_date' => $start_date,
                                    'term_end_date' => $expiry_date,
                                    'premium' => ($getPolicies->premium) ? $getPolicies->premium : null,
                                    'annual_premium' => $total_premium,
                                    'vat' => $getPolicies->vat,
                                    'vat_percent' => $getPolicies->vat_percent,
                                    'renewed_by' => NULL,
                                    'renewals_date' => Carbon::parse($expiry_date)->addDays(1)->format('Y-m-d'),
                                    'frequency' => $getPolicies->premium_freq,
                                    'first_premium' => $getPolicies->first_premium,
                                    'billing_start_date' => Carbon::now()->format('Y-m-d'),
                                    'policy_documents' => NULL,
                                    'policyActivatedDate' => $start_date,
                                    'payment_method' => $oldTermPaymentMethod,
                                    'payment_reference' => $getPolicies->id,
                                    'trans_type' => 'RENEW',
                                    'status' => $new_status,
                                    'created_at' => Carbon::now()->format('Y-m-d'),

                                    ];

                                $new_term_id = PolicyTerm::addPolicyTerm($newData);
                            }
                        }

                    }

                }
                // $term_data = PolicyTerm::where('policy_id',$getPolicies->id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->orderBy('id','desc')->first('id');

                // if (isset($new_term_id)) {
                //     if ($term_data->term_end_date < Carbon::now()) {
                //         $policyDetails->term_id = $new_term_id;
                //         $policyDetails->premium = ($policyDetails->premium) ? $policyDetails->premium : null;
                //         $policyDetails->first_premium = $policyDetails->first_premium;
                //         $policyDetails->premium_freq = $policyDetails->premium_freq;
                //         $policyDetails->policyActivatedDate = $start_date;
                //         $policyDetails->billingStartDate = $start_date;
                //         $policyDetails->vat = $policyDetails->vat;
                //         $policyDetails->vat_percent = $policyDetails->vat_percent;
                //         $policyDetails->term_start_date = $start_date;
                //         $policyDetails->term_end_date = $expiry_date;
                //         if (isset($policyRenewal)) {
                //             $policyDetails->expiry_date = $policyDetails->expiry_date;
                //             $policyDetails->sum_assured = $policyDetails->sum_assured;
                //         } else {
                //             $policyDetails->expiry_date = NULL;
                //             $policyDetails->sum_assured = NULL;
                //         }

                //         $term_id = null;
                //         if ($term_count == 0) {
                //             $term_id = $newBusiness_term_id;
                //         } else {
                //             $term_id = $term_data->id;
                //         }

                //         $document = new DocumentController();
                //         $generatePolicyDocument = $document->generatePolicyDocument($policyDetails->id);

                //         if ($generatePolicyDocument == true) {
                //             $sentBy = 'System';
                //             $getDocument =  $document->sendPolicyDocumentForRenew($policyDetails->id,$sentBy);
                //         }

                //     } else {
                //         if (isset($term_id)) {
                //             $policyDetails->term_id = $term_id;
                //         }
                //     }

                //     $policyDetails->expiry_date = Carbon::parse($start_date)->addYear()->format('Y-m-d');
                //     $policyDetails->save();

                //     $policyRenewal = PolicyRenewal::where('policyNumber',$policyDetails->policyNumber)->where('is_renewed',0)->first();

                //     if (isset($policyRenewal)) {
                //         $policyRenewal->is_renewed = 1;
                //         $policyRenewal->save();
                //     }

                // }

            }
        // } catch (\Exception $ex) {
        //     Log::info($ex->getMessage().' '.$ex->getLine());
        // }
    }

    public function renewMotorCompExpiredPolicy($policyNumber)
    {
        // try {
            $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$policyNumber)->first();
            $transactions = CustomerBanking::where('policy_id',$expired_policies_import->policy_id)->orderBy('id', 'DESC')->first();
            $policyDetails = Policy::with('customer')->where('policyNumber',$policyNumber)->first();
            $add = PolicyRenewal::where('policyNumber',$policyNumber)->first();
            if (!isset($add)) {
                $add = new PolicyRenewal();
            }

            $add->policy_id = $expired_policies_import->policy_id;
            $add->policyNumber = $expired_policies_import->policyNumber;
            $add->expiry_date = $expired_policies_import->expiry_date;
            $add->sms_sent = NULL;
            $add->email_sent = NULL;
            $add->claim_count = $expired_policies_import->claim_count;
            $add->paymentFrequency = $expired_policies_import->paymentFrequency;
            $add->paymentMethod = $expired_policies_import->paymentMethod;
            $add->days_remaining_to_expire = $expired_policies_import->days_remaining_to_expire;
            $add->old_premium = $expired_policies_import->old_premium;
            $add->new_premium = $expired_policies_import->new_premium;
            $add->is_rated = $expired_policies_import->is_rated;
            $add->sum_assured = $expired_policies_import->sum_assured;
            $add->request_data =$expired_policies_import->request_data;
            $add->response_data = $expired_policies_import->response_data;
            $add->save();

            // $request = new Request();
            // $request['policyNumber'] = $expired_policies_import->policyNumber;
            // $request['accountNumber'] = $transactions->accountNumber;
            // $request['BankName'] = $transactions->bankName;
            // $request['BranchCode'] = $transactions->branchCode;
            // $request['accountType'] = $transactions->accountType;
            // $request['policyID'] = $expired_policies_import->policy_id;
            // $request['frequency'] = $expired_policies_import->paymentFrequency;

            $policy_data = Policy::where('policyNumber',$policyNumber)->first();
            // $cancelPayment = $this->CancelPaymentsForPolicy($policy_data);
            // if ($expired_policies_import->paymentMethod == 'DPO') {
            //     $policyDetails['new_billing_start_date'] = Carbon::now()->format('Y-m-d');
            //     NewScheduleTransactionEvent::dispatch($policyDetails);
            // } elseif ($expired_policies_import->paymentMethod == 'RealPay' || $expired_policies_import->paymentMethod == 'Realpay') {
            //     $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            //     $createContract = $realpay->addAutoRenewContractForMotorComp($policy_data->id);
            // }

            $renewPolicy = $this->addTermsToRenewPolicies($policyNumber);
        // } catch (\Exception $ex) {
        //     Log::info($ex->getMessage().' '.$ex->getLine());
        // }
    }

    public function addTermsToRenewPolicies($policyNumber)
    {
        // try {
            $getRenewPolicies = ExpiredPoliciesImportJobs::where('policyNumber',$policyNumber)->first();
            if (isset($getRenewPolicies)) {
                $checkTerm = PolicyTerm::where('policy_id',$getRenewPolicies->policy_id)->count();
                $policyDetails = Policy::where('id',$getRenewPolicies->policy_id)->first();

                // $start_date = Carbon::now()->format('Y-m-d');
                // // $start_date  = Carbon::createFromFormat('Y-m-d', $today_date)->format('Y-m-d');
                // $expiry_date = Carbon::createFromFormat('Y-m-d', $start_date)->addYear()->subDays(1)->format('Y-m-d');

                // if (isset($getRenewPolicies->expiry_date) && Carbon::parse($getRenewPolicies->expiry_date)->lt(Carbon::now())) {
                //     $start_date  = Carbon::now()->format('Y-m-d');
                //     $expiry_date = Carbon::now()->addYear()->subDays(1)->format('Y-m-d');

                // } else {
                    $start_date  = Carbon::createFromFormat('Y-m-d', $getRenewPolicies->expiry_date)->addDays(1)->format('Y-m-d');
                    $expiry_date = Carbon::createFromFormat('Y-m-d', $start_date)->addYear()->subDays(1)->format('Y-m-d');
                // }

                $status = 'Deactive';

                $term_count = PolicyTerm::where('policy_id',$getRenewPolicies->policy_id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->count();

                if ($checkTerm == 0) {
                    $status = 'Deactive';

                    if (isset($getRenewPolicies->expiry_date)) {
                        if ($getRenewPolicies->expiry_date < Carbon::now()) {
                            $status = 'Deactive';
                        } else {
                            $status = 'Active';
                        }
                    }

                    $data = [
                        'policy_id' => $getRenewPolicies->policy_id,
                        'term_start_date' => ($policyDetails->policyActivatedDate) ? $policyDetails->policyActivatedDate : $policyDetails->created_at,
                        'term_end_date' => $getRenewPolicies->expiry_date,
                        'premium' => $policyDetails->premium,
                        'annual_premium' => $getRenewPolicies->old_premium,
                        'vat' => $policyDetails->vat,
                        'vat_percent' => $policyDetails->vat_percent,
                        'renewed_by' => NULL,
                        'renewals_date' => $getRenewPolicies->expiry_date,
                        'frequency' => $policyDetails->premium_freq,
                        'first_premium' => $policyDetails->first_premium,
                        'billing_start_date' => $policyDetails->billingStartDate,
                        'policy_documents' => NULL,
                        'policyActivatedDate' => $policyDetails->policyActivatedDate,
                        'payment_method' => $getRenewPolicies->paymentMethod,
                        'payment_reference' => $getRenewPolicies->policy_id,
                        'trans_type' => 'NEW BUSINESS',
                        'status' => $status,
                        'created_at' => Carbon::now()->format('Y-m-d'),

                    ];
                    $newBusiness_term_id = PolicyTerm::addPolicyTerm($data);
                }


                // policy new term data

                $policyPremium = unserialize($getRenewPolicies->response_data);

                $product = Product::where('id', $policyDetails->product_id)->first(array('has_vehicle', 'has_member', 'premium_type_id', 'limit', 'kyc_recipient', 'kyc_customer', 'preinspection', 'is_motor_items', 'region_id', 'has_activation_code', 'type'));
                $regionVat = Region::where('id', $product->region_id)->first('vat');

                $term_permium = null;
                switch ($policyDetails->premium_freq) {
                    case 1:
                        $term_permium = $policyPremium['month_ins'];
                        break;

                    case 2:
                        $term_permium = $policyPremium['three_ins'];
                        break;

                    case 3:
                        $term_permium = $policyPremium['annual_ins'];
                        break;

                        default:
                    break;
                }

                $premium_value = ($term_permium) ? $term_permium : $null;
                $premium_without_vat = $premium_value / (1 + ($regionVat->vat / 100));
                $vat = number_format($premium_value - $premium_without_vat, 2);

                $new_status = 'Deactive';
                if ($status == 'Deactive') {
                    if ($expiry_date > Carbon::now()) {
                        $new_status = 'Deactive';
                    } else {
                        $new_status = 'Active';
                    }
                }

                $newData = [
                    'policy_id' => $getRenewPolicies->policy_id,
                    'term_start_date' => $start_date,
                    'term_end_date' => $expiry_date,
                    'premium' => ($policyDetails->premium) ? $policyDetails->premium : null,
                    'annual_premium' => $getRenewPolicies->old_premium,
                    'vat' => $policyDetails->vat,
                    'vat_percent' => $policyDetails->vat_percent,
                    'renewed_by' => NULL,
                    'renewals_date' => Carbon::parse($expiry_date)->addDays(1)->format('Y-m-d'),
                    'frequency' => $policyDetails->premium_freq,
                    'first_premium' => $policyDetails->first_premium,
                    'billing_start_date' => Carbon::now()->format('Y-m-d'),
                    'policy_documents' => NULL,
                    'policyActivatedDate' => $start_date,
                    'payment_method' => $getRenewPolicies->paymentMethod,
                    'payment_reference' => $getRenewPolicies->policy_id,
                    'trans_type' => 'RENEW',
                    'status' => $new_status,
                    'created_at' => Carbon::now()->format('Y-m-d'),

                    ];

                $new_term_id = PolicyTerm::addPolicyTerm($newData);

                $term_data = PolicyTerm::where('policy_id',$policyDetails->id)->whereIn('trans_type',['NEW BUSINESS','RENEW'])->orderBy('id','desc')->first();

                if (isset($new_term_id)) {
                    if ($term_data->term_end_date < Carbon::now()) {
                        $policyDetails->term_id = $new_term_id;
                        $policyDetails->premium = ($policyDetails->premium) ? $policyDetails->premium : null;
                        $policyDetails->first_premium = $policyDetails->first_premium;
                        $policyDetails->premium_freq = $policyDetails->premium_freq;
                        $policyDetails->policyActivatedDate = $start_date;
                        $policyDetails->billingStartDate = $start_date;
                        $policyDetails->vat = $policyDetails->vat;
                        $policyDetails->vat_percent = $policyDetails->vat_percent;
                        $policyDetails->term_start_date = $start_date;
                        $policyDetails->term_end_date = $expiry_date;
                        if (isset($policyRenewal)) {
                            $policyDetails->expiry_date = $policyDetails->expiry_date;
                            $policyDetails->sum_assured = $policyDetails->sum_assured;
                        } else {
                            $policyDetails->expiry_date = NULL;
                            $policyDetails->sum_assured = NULL;
                        }

                        $term_id = null;
                        if ($term_count == 0) {
                            $term_id = $newBusiness_term_id;
                        } else {
                            $term_id = $term_data->id;
                        }

                        $document = new DocumentController();
                        $generatePolicyDocument = $document->generatePolicyDocument($policyDetails->id);

                        // if ($generatePolicyDocument == true) {
                        //     $sentBy = 'System';
                        //     $getDocument =  $document->sendPolicyDocumentForRenew($policyDetails->id,$sentBy);
                        // }

                    } else {
                        if (isset($term_id)) {
                            $policyDetails->term_id = $term_id;
                        }
                    }

                    $policyDetails->expiry_date = Carbon::parse($start_date)->addYear()->format('Y-m-d');
                    $policyDetails->save();

                    $policyRenewal = PolicyRenewal::where('policyNumber',$policyDetails->policyNumber)->where('is_renewed',0)->first();

                    if (isset($policyRenewal)) {
                        $policyRenewal->is_renewed = 1;
                        $policyRenewal->save();
                    }

                }

            }
        // } catch (\Exception $ex) {
        //     Log::info($ex->getMessage().' '.$ex->getLine());
        // }
    }


    public static function checkMotorpolicyStatus($product_id,$policyId,$set){
        $policy = Policy::where('id',$policyId)->first();
        $vehicle = Vehicle::where('policy_id',$policyId)->where('status','1')->first();
        Log::info(json_encode($vehicle));
        Log::info(json_encode($set));
        Log::info(json_encode($product_id));
        $cellphone=PolicyCellPhone::where('policy_id',$policyId)->where('status','1')->first();
        $payment = PaymentTransaction::where('policyNumber',$policy->policyNumber)->where('status','Success')->orderBy('id','asc')->first();
        if($product_id==3 && isset($payment) && $set==1){
        return 1;
        }else if($product_id==3 &&  $set==2 && isset($vehicle)){
            return 1;
        }else if($product_id==5 && isset($payment) && $set==1){
            return 1;
        }
        else if($product_id==5 && $set==2 && isset($cellphone)){
            return 1;
        }
        else{
            return 0;
        }
    }
      /**
     * Generate ledger entries for a specific policy
     * 
     * @param int $policyId The policy ID to generate ledger for
     * @return array Returns success status and message
     */
    public function generateLedgerForPolicy($policyId)
    {
        try {
            ini_set('max_execution_time', 0);
            
            // Fetch the policy
            $policy = Policy::where('id', $policyId)
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 
                    'first_premium_wvat', 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 
                    'is_sys_act_generated', 'billingStartDate', 'ori_billingStartDate', 'status'))
                ->first();
            
            if (!$policy) {
                return ['success' => false, 'message' => 'Policy not found'];
            }
            
            DB::beginTransaction();
            
            $now = Carbon::now();
            $created_date = Carbon::parse($policy->created_at);
            
            // For Motor Comp First Invoice on Policy Activation dates
            if ($policy->product_id != 3 && $policy->billingStartDate != NULL) {
                $pos = strpos($policy->billingStartDate, '/');
                if ($pos !== false) {
                    $created_date = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate);
                } else {
                    $created_date = Carbon::parse($policy->billingStartDate);
                }
            } elseif ($policy->policyActivatedDate != NULL) {
                $created_date = Carbon::parse($policy->policyActivatedDate);
            }
            
            $ledger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
            $ledger_count = Ledger::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
            
            if ($ledger == NULL) {
                $ledger = \AlphaDirect\Models\LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                $ledger_count = \AlphaDirect\Models\LedgerArchive::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
            }
            
            if ($ledger_count == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == NULL || $policy->premium_freq == '')) {
                $pos = strpos($policy->billingStartDate, '/');
                if ($pos !== false) {
                    $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate);
                } else {
                    $policy->billingStartDate = Carbon::parse($policy->billingStartDate);
                }
                $diff = Carbon::parse($policy->created_at)->diffInMonths(Carbon::parse($policy->billingStartDate));
                
                if ($diff == 0) {
                    if ($policy->policyActivatedDate != NULL && Carbon::parse($policy->policyActivatedDate)->lt(Carbon::parse($policy->billingStartDate))) {
                        $created_date = Carbon::parse($policy->policyActivatedDate);
                    }
                }
                if ($diff > 1) {
                    if ($policy->policyActivatedDate != NULL) {
                        $created_date = Carbon::parse($policy->policyActivatedDate);
                    } else {
                        $created_date = Carbon::parse($policy->created_at);
                    }
                }
            }
            
            if ($ledger != NULL) {
                $invoice_no = $ledger->invoice_no;
                $invoice_no++;
                $banking_id = $ledger->banking_id;
            } else {
                $invoice_no = $policy->policyNumber . '-' . sprintf('%03d', 1);
                $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                if ($banking_id != NULL)
                    $banking_id = $banking_id->id;
                else
                    $banking_id = NULL;
            }
            
            $diff_in_months_old_term = 0;
            $term = null;
            
            if ($policy->product_id == 3 && $policy->premium_freq == 2) { // 3 Installments, Motor Comp
                $ledger_count_archive = 0;
                
                if ($ledger_count > 0)
                    $created_date = Carbon::parse(str_replace("/", '-', $policy->billingStartDate))->format('Y-m-d');
                
                $diff_in_years = $now->diffInYears($created_date);
                $diff_in_months = $now->diffInMonths($created_date);
                
                if ($diff_in_months == 0)
                    $diff_in_months++;
                if ($diff_in_years == 0)
                    $diff_in_years++;
                if ($diff_in_months > 3) {
                    $diff_in_months = 3;
                    $ledger_count_archive = \AlphaDirect\Models\LedgerArchive::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->count();
                }
                
                $diff_in_months = $diff_in_months - ($ledger_count + $ledger_count_archive);
            } elseif ($policy->product_id == 3 && $policy->premium_freq == 3) { // Yearly, Motor Comp
                $diff_in_months = $now->format('Y') - $created_date->format('Y');
                
                if ($ledger_count == 0 && $diff_in_months == 0)
                    $diff_in_months = 1;
                
                if ($ledger_count > 0 && $term == null)
                    $created_date = Carbon::parse(str_replace("/", '-', $policy->billingStartDate))->format('Y-m-d');
            } else {
                $diff_in_months = $now->diffInMonths($created_date);
            }
            
            $first_invoice = 0;
            
            // If billing date is in same month and date is less than created date then create 2 invoices
            if ($policy->product_id == 3 && $policy->premium_freq == 1 && $ledger_count == 0 && 
                (Carbon::parse(str_replace("/", '-', $policy->billingStartDate))->format('Y-m-d') > Carbon::parse($created_date)->format('Y-m-d'))) {
                $diff_in_months++;
                $first_invoice = 1;
            }
            
            if ($policy->product_id == 3 && $policy->premium_freq == 2) {
                if ($diff_in_months != (($diff_in_years * 3) - $ledger_count) && $ledger_count >= 3)
                    $diff_in_months = $diff_in_months - $ledger_count;
            }
            
            $vatCheckDate = Carbon::parse('2021-03-31')->format('Y-m-d');
            $vatCheckDate2 = Carbon::parse('2022-08-01')->format('Y-m-d');
            $vatCheckDateCount = 0;
            $is_policy_renewed = 0;
            
            // For Renew
            if ($policy->product_id == 3 && $ledger_count > 0 && $term == NULL) {
                $renew = PolicyRenewal::where('policy_id', $policy->id)->first(array('is_renewed'));
                if ($renew != NULL && $renew->is_renewed == 1) {
                    $is_policy_renewed = 1;
                    if ($renew->is_renewed == 0) {
                        if ($ledger_count > 0)
                            $diff_in_months = 0;
                    } else if ($policy->premium_freq == 2 || $policy->premium_freq == 3) {
                        $first_invoice = 0;
                        $term = PolicyTerm::where('policy_id', $policy->id)->count();
                        $diff_in_months = $term - $ledger_count;
                    }
                    $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                    $latest_term = PolicyTerm::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(array('term_start_date', 'billing_start_date'));
                    if ($latest_term != NULL)
                        $ledger_count = Ledger::where('policy_id', $policy->id)->where('accounting_date', '>=', $latest_term->term_start_date)->where('trans_type', 'Invoice')->count();
                    
                    $pos = strpos($latest_term->billing_start_date, '/');
                    if ($pos !== false) {
                        $created_date = Carbon::createFromFormat('d/m/Y', $latest_term->billing_start_date);
                    } else {
                        $created_date = Carbon::parse($latest_term->billing_start_date);
                    }
                }
                
                $policyPremium = $policy->premium;
                if ($policy->premium_freq == 1)
                    $policyPremium = $policyPremium / 1.08;
                $policyPremium = $policyPremium / (1 + ($policy->vat_percent / 100));
                $policy->vat = number_format($policy->premium - $policyPremium, 2);
            }
            
            // If Billing date gets updated and Invoices dont get generated
            if ($is_policy_renewed == 0 && $policy->product_id != 3 && $ledger_count == 0 && $term == NULL) {
                if ($policy->ori_billingStartDate != NULL)
                    $created_date = Carbon::parse($policy->ori_billingStartDate)->format('Y-m-d');
                else {
                    $diff = Carbon::parse($policy->created_at)->diffInMonths($policy->billingStartDate);
                    if ($diff > 12) {
                        $before = \AlphaDirect\BeforeUpdatePolicy::where('customer_id', $policy->customer_id)->where('product_id', $policy->product_id)->orderBy('id', 'asc')->first(array('billingStartDate'));
                        if ($before != NULL && $before->billingStartDate != NULL) {
                            $posCheck = strpos($before->billingStartDate, '/');
                            if ($posCheck !== false) {
                                $created_date = Carbon::createFromFormat('d/m/Y', $before->billingStartDate)->format('Y-m-d');
                            } else {
                                $created_date = Carbon::parse($before->billingStartDate)->format('Y-m-d');
                            }
                        }
                    }
                }
            }
            
            if ($ledger_count > 0 && $policy->premium_freq != 3)
                $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
            elseif ($policy->premium_freq != 2 && $policy->premium_freq != 3)
                $diff_in_months++;
            
            $premium = $policy->premium;
            $vat = $policy->vat;
            $billingSameAsCreatedDate = 0;
            $original_created_date = $created_date;
            
            if ($policy->product_id == 3 && $policy->premium_freq == 3 && $ledger_count > 0 && $is_policy_renewed == 0 && $term == NULL) {
                $created_date = Carbon::parse($original_created_date)->addYearsNoOverflow($ledger_count);
                Carbon::parse($created_date)->format('Y-m-d');
            }
            
            if (($policy->premium_freq == 1 || $policy->premium_freq == NULL) && $diff_in_months == 0)
                $diff_in_months++;
            
            if ($policy->premium_freq == 2 && $diff_in_months == 0 && $diff_in_years > 1)
                $diff_in_months++;
            
            // Deactivated Policy if No transaction no Invoice
            if ($policy->status == 0) {
                $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->count();
                if ($transaction == 0)
                    $diff_in_months = 0;
            }
            
            if ($diff_in_months_old_term != 0)
                $diff_in_months = $diff_in_months_old_term;
            
            // If Policy is not renewed dont generate invoices more than 12
            if ($is_policy_renewed == 0 && $policy->product_id == 3) {
                $checkMonths = Carbon::parse($created_date)->diffInMonths($policy->policyActivatedDate);
                if ($checkMonths >= 12)
                    $diff_in_months = 0;
                
                if ($ledger_count == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == NULL || $policy->premium_freq == '')) {
                    if ($first_invoice == 1)
                        $diff_in_months = 13;
                    else
                        $diff_in_months = 12;
                }
            }
            
            // To avoid duplicate invoices
            if ($ledger != NULL && Carbon::parse($ledger->invoice_date)->gt($created_date)) {
                $created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');
                $original_created_date = $created_date;
            }
            
            $invoicesGenerated = 0;
            
            // Generate invoices
            for ($i = 0; $i < $diff_in_months; $i++) {
                $billingStartDate = Carbon::parse(str_replace("/", '-', $policy->billingStartDate))->format('Y-m-d');
                if ($billingStartDate == Carbon::parse($created_date)->format('Y-m-d') || $policy->billingStartDate == NULL)
                    $billingSameAsCreatedDate = 1;
                
                if ($policy->product_id == 3 && $policy->premium_freq != 2 && $policy->premium_freq != 3 && $i == 0 && $ledger_count == 0 && $billingSameAsCreatedDate == 0 && $is_policy_renewed == 0) {
                    if ($policy->first_premium_wvat == NULL || $policy->first_premium_wvat == "" || $policy->first_premium_wvat < 1)
                        $policy->first_premium_wvat = $policy->premium;
                    $policy->premium = $policy->first_premium_wvat;
                    $firstPremium = $policy->first_premium_wvat;
                    if ($policy->premium_freq == 1)
                        $firstPremium = $firstPremium / 1.08;
                    $firstPremium = $firstPremium / (1 + ($policy->vat_percent / 100));
                    $policy->vat = number_format($policy->first_premium_wvat - $firstPremium, 2);
                } elseif ($policy->product_id == 3 && $i > 0 && $is_policy_renewed == 0) {
                    if ($vatCheckDateCount == 0) {
                        $policy->premium = $premium;
                        $policy->vat = $vat;
                    }
                }
                
                if ($policy->premium_freq != 3) {
                    if ($i == 0)
                        Carbon::parse($created_date)->format('Y-m-d');
                    elseif ($i == 1 && $first_invoice == 1) {
                        $created_date = Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                        $original_created_date = Carbon::parse($policy->billingStartDate)->format('Y-m-d');
                    } elseif ($i >= 1) {
                        if ($first_invoice == 1) {
                            $created_date = Carbon::parse($original_created_date)->addMonthsNoOverflow($i - 1);
                            Carbon::parse($created_date)->format('Y-m-d');
                        } else {
                            $created_date = Carbon::parse($original_created_date)->addMonthsNoOverflow($i);
                            Carbon::parse($created_date)->format('Y-m-d');
                        }
                    }
                } else {
                    if ($i >= 1) {
                        if ($is_policy_renewed == 1) {
                            $created_date = Carbon::parse($original_created_date)->addYearsNoOverflow($i);
                            Carbon::parse($created_date)->format('Y-m-d');
                        } else {
                            break;
                        }
                    }
                }
                
                if (Carbon::parse($created_date)->gt($vatCheckDate) && Carbon::parse($created_date)->lt($vatCheckDate2) && $policy->vat_percent == 12 && $vatCheckDateCount == 0 && $policy->premium_freq == 1) {
                    $vatCheckDateCount = 1;
                    if ($policy->product_id == 3) {
                        $product = Product::where('id', $policy->product_id)->first(array('region_id'));
                        $region_vat = Region::where('id', $product->region_id)->first(array('vat'))->vat;
                        $policyPremium = $policy->premium;
                        if ($policy->premium_freq == 1)
                            $policyPremium = $policyPremium / 1.08;
                        $policyPremium = $policyPremium / 1.12;
                        
                        $premiumWithoutVAT = $policyPremium;
                        $policyPremium = $premiumWithoutVAT * (1 + ($region_vat / 100));
                        if ($policy->premium_freq == 1)
                            $policyPremium = $policyPremium * 1.08;
                        $policy->premium = $policyPremium;
                        $policy->vat = number_format($policyPremium - $premiumWithoutVAT, 2);
                    } else {
                        $plan = Productplan::where('id', $policy->plan_id)->first(array('premium'));
                        $policy->vat = number_format($policy->premium - $plan->premium, 2);
                    }
                }
                
                $data = array();
                $record = array();
                $subData = array();
                $subRecord = array();
                
                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                if ($balance != null) {
                    $balance = $balance->balance;
                } else {
                    $balance = \AlphaDirect\Models\LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                    if ($balance != null) {
                        $balance = $balance->balance;
                    } else {
                        $balance = 0;
                    }
                }
                
                $cancelled_status = false;
                if ($policy->status == 2) {
                    $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('status', 'Success')->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
                    if ($transaction != NULL) {
                        $pos2 = strpos($transaction->paymentDate, 'T');
                        $pos = strpos($transaction->paymentDate, ':');
                        if ($pos2 !== false) {
                            $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d H:i:s');
                        } elseif ($pos !== false) {
                            $pos3 = strpos($transaction->paymentDate, '/');
                            if ($pos3 !== false) {
                                if (substr_count($transaction->paymentDate, ":") > 1) {
                                    $date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                } else {
                                    $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                }
                            } else {
                                if (substr_count($transaction->paymentDate, ":") > 1) {
                                    $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                } else {
                                    $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                }
                            }
                        } else {
                            $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d H:i:s');
                        }
                        $cancelled_date = Carbon::parse($date)->addDay()->format('Y-m-d H:i:s');
                        Carbon::parse($created_date)->format('Y-m-d');
                        $cancelled_status = Carbon::parse($created_date)->lte($cancelled_date);
                    }
                } elseif ($policy->status == 0) {
                    $cancelled_status = false;
                    $cancelled_date = $policy->updated_at;
                    $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)->where('is_ledger', 0)->where('amount', '!=', 1)->where('status', 'Success')->orderBy('paymentDate', 'desc')->first(array('paymentDate'));
                    if ($transaction != NULL) {
                        $pos2 = strpos($transaction->paymentDate, 'T');
                        $pos = strpos($transaction->paymentDate, ':');
                        if ($pos2 !== false) {
                            $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d H:i:s');
                        } elseif ($pos !== false) {
                            $pos3 = strpos($transaction->paymentDate, '/');
                            if ($pos3 !== false) {
                                if (substr_count($transaction->paymentDate, ":") > 1) {
                                    $date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                } else {
                                    $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                }
                            } else {
                                if (substr_count($transaction->paymentDate, ":") > 1) {
                                    $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                } else {
                                    $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d H:i:s');
                                }
                            }
                        } else {
                            $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d H:i:s');
                        }
                        $cancelled_date = Carbon::parse($date)->addDay()->format('Y-m-d H:i:s');
                        $cancelled_status = Carbon::parse($created_date)->lte($cancelled_date);
                    } else {
                        $cancelled_status = false;
                    }
                } elseif ($policy->status == 1) {
                    $cancelled_status = true;
                }
                
                // Check if Invoice with same date already exists
                $check_invoice_exists = Ledger::where('policy_id', $policy->id)->where('trans_type', 'Invoice')->where('invoice_date', $created_date)->count();
                
                if (Carbon::parse($created_date)->lte($now) && $cancelled_status && $check_invoice_exists == 0) {
                    // PREMIUM
                    $record['customer_id'] = $policy->customer_id;
                    $record['account_id'] = NULL;
                    $record['policy_id'] = $policy->id;
                    $record['claim_id'] = NULL;
                    $record['banking_id'] = $banking_id;
                    $record['account_name'] = NULL;
                    $record['accounting_date'] = Carbon::parse($created_date);
                    $record['trans_type'] = 'Invoice Premium';
                    $record['amount_type'] = NULL;
                    $record['trans_ref'] = NULL;
                    $record['orig_trans'] = NULL;
                    $record['unallocated'] = NULL;
                    $record['system_date'] = Carbon::parse($created_date);
                    $record['trans_sub_type'] = NULL;
                    $record['eff_date'] = Carbon::parse($created_date);
                    $record['invoice_file'] = NULL;
                    $record['invoice_date'] = NULL;
                    $record['invoice_no'] = NULL;
                    $record['invoice_amount'] = NULL;
                    $record['premium'] = $policy->premium;
                    $record['other_charges'] = NULL;
                    $record['due_amount'] = NULL;
                    $record['pmts_adjust'] = NULL;
                    $record['due_date'] = NULL;
                    $record['status'] = 'Pending';
                    $record['credit'] = NULL;
                    
                    $amt = str_replace(',', '', number_format(((float) $policy->premium - (float) $policy->vat), 2));
                    $record['debit'] = str_replace(',', '', $amt);
                    $balance = number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2);
                    $record['balance'] = str_replace(',', '', $balance);
                    
                    $data[] = $record;
                    
                    // SUB-LEDGER
                    $subRecord['customer_id'] = $policy->customer_id;
                    $subRecord['account_id'] = NULL;
                    $subRecord['policy_id'] = $policy->id;
                    $subRecord['claim_id'] = NULL;
                    $subRecord['banking_id'] = $banking_id;
                    $subRecord['account_name'] = 'Insurance Sales A/C';
                    $subRecord['accounting_date'] = Carbon::parse($created_date);
                    $subRecord['trans_type'] = 'Insurance Premium';
                    $subRecord['trans_ref'] = NULL;
                    $subRecord['system_date'] = Carbon::parse($created_date);
                    $subRecord['credit'] = $amt;
                    $subRecord['debit'] = NULL;
                    
                    $subData[] = $subRecord;
                    
                    // VAT
                    $record = array();
                    
                    $record['customer_id'] = $policy->customer_id;
                    $record['account_id'] = NULL;
                    $record['policy_id'] = $policy->id;
                    $record['claim_id'] = NULL;
                    $record['banking_id'] = $banking_id;
                    $record['account_name'] = NULL;
                    $record['accounting_date'] = Carbon::parse($created_date);
                    $record['trans_type'] = 'Invoice VAT';
                    $record['amount_type'] = NULL;
                    $record['trans_ref'] = NULL;
                    $record['orig_trans'] = NULL;
                    $record['unallocated'] = NULL;
                    $record['system_date'] = Carbon::parse($created_date);
                    $record['trans_sub_type'] = NULL;
                    $record['eff_date'] = Carbon::parse($created_date);
                    $record['invoice_file'] = NULL;
                    $record['invoice_date'] = NULL;
                    $record['invoice_no'] = NULL;
                    $record['invoice_amount'] = NULL;
                    $record['premium'] = $policy->premium;
                    $record['other_charges'] = NULL;
                    $record['due_amount'] = NULL;
                    $record['pmts_adjust'] = NULL;
                    $record['due_date'] = NULL;
                    $record['status'] = 'Pending';
                    $record['credit'] = NULL;
                    
                    $amt = floatval($policy->vat);
                    
                    $record['debit'] = str_replace(',', '', $amt);
                    $balance = str_replace(',', '', number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2));
                    $record['balance'] = str_replace(',', '', $balance);
                    
                    $data[] = $record;
                    
                    // SUB-LEDGER
                    $subRecord = array();
                    
                    $subRecord['customer_id'] = $policy->customer_id;
                    $subRecord['account_id'] = NULL;
                    $subRecord['policy_id'] = $policy->id;
                    $subRecord['claim_id'] = NULL;
                    $subRecord['banking_id'] = $banking_id;
                    $subRecord['account_name'] = 'VAT Control A/C';
                    $subRecord['accounting_date'] = Carbon::parse($created_date);
                    $subRecord['trans_type'] = 'VAT on Insurance Premium';
                    $subRecord['trans_ref'] = NULL;
                    $subRecord['system_date'] = Carbon::parse($created_date);
                    $subRecord['credit'] = $amt;
                    $subRecord['debit'] = NULL;
                    
                    $subData[] = $subRecord;
                    
                    // INVOICE
                    $record = array();
                    $record['customer_id'] = $policy->customer_id;
                    $record['account_id'] = NULL;
                    $record['policy_id'] = $policy->id;
                    $record['claim_id'] = NULL;
                    $record['banking_id'] = $banking_id;
                    $record['account_name'] = NULL;
                    $record['accounting_date'] = Carbon::parse($created_date);
                    $record['trans_type'] = 'Invoice';
                    $record['amount_type'] = NULL;
                    $record['trans_ref'] = NULL;
                    $record['orig_trans'] = NULL;
                    $record['unallocated'] = NULL;
                    $record['system_date'] = Carbon::parse($created_date);
                    $record['trans_sub_type'] = NULL;
                    $record['eff_date'] = Carbon::parse($created_date);
                    $record['invoice_file'] = 1;
                    $record['invoice_date'] = Carbon::parse($created_date);
                    $record['invoice_no'] = $invoice_no;
                    $record['invoice_amount'] = $policy->premium;
                    $record['premium'] = $policy->premium;
                    $record['other_charges'] = NULL;
                    $record['due_amount'] = $policy->premium;
                    $record['pmts_adjust'] = NULL;
                    $record['due_date'] = NULL;
                    $record['status'] = 'Pending';
                    $record['credit'] = NULL;
                    
                    $amt = number_format(((float) str_replace(',', '', $policy->premium) - (float) str_replace(',', '', $policy->vat)), 2);
                    
                    $record['debit'] = $policy->premium;
                    $record['balance'] = str_replace(',', '', $balance);
                    
                    $data[] = $record;
                    
                    // SUB-LEDGER
                    $subRecord = array();
                    $subRecord['customer_id'] = $policy->customer_id;
                    $subRecord['account_id'] = NULL;
                    $subRecord['policy_id'] = $policy->id;
                    $subRecord['claim_id'] = NULL;
                    $subRecord['banking_id'] = $banking_id;
                    $subRecord['account_name'] = 'Accounts Receivable A/C';
                    $subRecord['accounting_date'] = Carbon::parse($created_date);
                    $subRecord['trans_type'] = 'Accounts Receivable';
                    $subRecord['trans_ref'] = NULL;
                    $subRecord['system_date'] = Carbon::parse($created_date);
                    $subRecord['credit'] = NULL;
                    $subRecord['debit'] = $policy->premium;
                    
                    $subData[] = $subRecord;
                    
                    $subData[0]['trans_ref'] = $invoice_no;
                    $subData[1]['trans_ref'] = $invoice_no;
                    $subData[2]['trans_ref'] = $invoice_no;
                    
                    $invoice_no++;
                    
                    // Transactions
                    $transactions = PaymentTransaction::where('policyNumber', $policy->policyNumber)
                        ->where('is_ledger', 0)
                        ->where('amount', '!=', 1)
                        ->orderBy('paymentDate', 'asc')
                        ->get();
                    
                    foreach ($transactions as $transactionKey => $transaction) {
                        $checkDuplicateTransaction = Ledger::where('trans_ref', $transaction->referenceNumber)->count();
                        if ($checkDuplicateTransaction == 0) {
                            $pos2 = strpos($transaction->paymentDate, 'T');
                            $pos = strpos($transaction->paymentDate, ':');
                            if ($pos2 !== false) {
                                $date = Carbon::parse($transaction->paymentDate)->setTimezone('UTC')->format('Y-m-d');
                            } elseif ($pos !== false) {
                                $pos3 = strpos($transaction->paymentDate, '/');
                                if ($pos3 !== false) {
                                    if (substr_count($transaction->paymentDate, ":") > 1) {
                                        $date = Carbon::createFromFormat('d/m/Y h:i:s a', $transaction->paymentDate)->format('Y-m-d');
                                    } else {
                                        $date = Carbon::createFromFormat('d/m/Y H:i', $transaction->paymentDate)->format('Y-m-d');
                                    }
                                } else {
                                    if (substr_count($transaction->paymentDate, ":") > 1) {
                                        $date = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->paymentDate)->format('Y-m-d');
                                    } else {
                                        $date = Carbon::createFromFormat('Y-m-d H:i', $transaction->paymentDate)->format('Y-m-d');
                                    }
                                }
                            } else {
                                $date = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                            }
                            
                            $transaction_date_check = false;
                            $checkDate = Carbon::parse($created_date);
                            $checkDate2 = Carbon::parse($created_date);
                            if ($first_invoice == 0 || $policy->premium_freq == 1 || $policy->premium_freq == NULL)
                                $transaction_date_check = Carbon::parse($date)->lte($checkDate->endOfMonth());
                            else
                                $transaction_date_check = Carbon::parse($date)->lte($created_date);
                            
                            if (!$transaction_date_check && $i > 1) {
                                $transaction_date_check = Carbon::parse($date)->lte($checkDate2->subDay()->addMonth());
                            }
                            
                            if ($transaction_date_check) {
                                if ($transaction->is_refund == 0) {
                                    if ($transaction->amount != 1) {
                                        $record = array();
                                        $record['customer_id'] = $policy->customer_id;
                                        $record['account_id'] = NULL;
                                        $record['policy_id'] = $policy->id;
                                        $record['claim_id'] = NULL;
                                        $record['banking_id'] = $banking_id;
                                        $record['account_name'] = NULL;
                                        $record['accounting_date'] = $date;
                                        $record['trans_type'] = 'Payment';
                                        $record['amount_type'] = NULL;
                                        $record['trans_ref'] = $transaction->referenceNumber;
                                        $record['orig_trans'] = $transaction->referenceNumber;
                                        $record['unallocated'] = NULL;
                                        $record['system_date'] = $date;
                                        $record['trans_sub_type'] = NULL;
                                        $record['eff_date'] = $date;
                                        $record['invoice_file'] = NULL;
                                        $record['invoice_date'] = NULL;
                                        $record['invoice_no'] = NULL;
                                        $record['invoice_amount'] = NULL;
                                        $record['premium'] = $policy->premium;
                                        $record['other_charges'] = NULL;
                                        $record['due_amount'] = NULL;
                                        $record['pmts_adjust'] = NULL;
                                        $record['due_date'] = Carbon::parse($data[2]['system_date'])->addMonthsNoOverflow()->format('Y-m-d');
                                        $record['status'] = 'Paid';
                                        $record['debit'] = NULL;
                                        
                                        if ($transaction->status == 'Success' || $transaction->status == 'SUCCESS' || $transaction->status == 'S') {
                                            $transactionAmount = str_replace(',', '', (float) $transaction->amount);
                                            $record['credit'] = $transaction->amount;
                                            if ($balance < 0) {
                                                $record['balance'] = str_replace(',', '', number_format(($transactionAmount - abs($balance)), 2));
                                                $balance = str_replace(',', '', number_format(($transactionAmount - abs($balance)), 2));
                                            } else {
                                                $record['balance'] = str_replace(',', '', number_format(($balance + $transactionAmount), 2));
                                                $balance = str_replace(',', '', number_format(($balance + $transactionAmount), 2));
                                            }
                                        } else {
                                            $record['credit'] = 0;
                                            $record['balance'] = $balance;
                                        }
                                        
                                        $data[] = $record;
                                        
                                        $transaction->is_ledger = 1;
                                        $transaction->save();
                                        
                                        if ($transactionKey == 0) {
                                            $data[0]['trans_ref'] = $transaction->referenceNumber;
                                            $data[0]['orig_trans'] = $transaction->referenceNumber;
                                            $data[1]['trans_ref'] = $transaction->referenceNumber;
                                            $data[1]['orig_trans'] = $transaction->referenceNumber;
                                            $data[2]['trans_ref'] = $transaction->referenceNumber;
                                            $data[2]['orig_trans'] = $transaction->referenceNumber;
                                            $data[2]['due_date'] = Carbon::parse($data[2]['system_date'])->addMonthsNoOverflow()->format('Y-m-d');
                                        }
                                        
                                        if ($transaction->status == 'Success' || $transaction->status == 'SUCCESS' || $transaction->status == 'S') {
                                            // SUB-LEDGER
                                            $subRecord = array();
                                            
                                            $subRecord['customer_id'] = $policy->customer_id;
                                            $subRecord['account_id'] = NULL;
                                            $subRecord['policy_id'] = $policy->id;
                                            $subRecord['claim_id'] = NULL;
                                            $subRecord['banking_id'] = $banking_id;
                                            $subRecord['account_name'] = 'Accounts Receivable A/C';
                                            $subRecord['accounting_date'] = Carbon::parse($created_date);
                                            $subRecord['trans_type'] = 'Accounts Receivable';
                                            $subRecord['trans_ref'] = $transaction->referenceNumber;
                                            $subRecord['system_date'] = Carbon::parse($created_date);
                                            $subRecord['credit'] = $transaction->amount;
                                            $subRecord['debit'] = NULL;
                                            
                                            $subData[] = $subRecord;
                                            
                                            $subRecord = array();
                                            
                                            $subRecord['customer_id'] = $policy->customer_id;
                                            $subRecord['account_id'] = NULL;
                                            $subRecord['policy_id'] = $policy->id;
                                            $subRecord['claim_id'] = NULL;
                                            $subRecord['banking_id'] = $banking_id;
                                            $subRecord['account_name'] = 'Bank A/C';
                                            $subRecord['accounting_date'] = Carbon::parse($created_date);
                                            $subRecord['trans_type'] = 'Cash Received';
                                            $subRecord['trans_ref'] = $transaction->referenceNumber;
                                            $subRecord['system_date'] = Carbon::parse($created_date);
                                            $subRecord['credit'] = NULL;
                                            $subRecord['debit'] = $transaction->amount;
                                            
                                            $subData[] = $subRecord;
                                            
                                            if ($transactionKey == 0) {
                                                $data[0]['status'] = 'Paid';
                                                $data[1]['status'] = 'Paid';
                                                $data[2]['status'] = 'Paid';
                                                $data[2]['pmts_adjust'] = $transaction->amount;
                                            }
                                        } else {
                                            if ($transactionKey == 0) {
                                                $data[0]['status'] = 'Pending';
                                                $data[1]['status'] = 'Pending';
                                                $data[2]['status'] = 'Pending';
                                                $data[3]['trans_type'] = 'Payment Failed';
                                            } else {
                                                $data[count($data) - 1]['trans_type'] = 'Payment Failed';
                                            }
                                        }
                                        
                                        $transaction->is_ledger = 1;
                                        $transaction->save();
                                    }
                                } else {
                                    // Refund
                                    $record = array();
                                    $record['customer_id'] = $policy->customer_id;
                                    $record['account_id'] = NULL;
                                    $record['policy_id'] = $policy->id;
                                    $record['claim_id'] = NULL;
                                    $record['banking_id'] = NULL;
                                    $record['account_name'] = NULL;
                                    $record['accounting_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $record['trans_type'] = 'Refund';
                                    $record['amount_type'] = NULL;
                                    $record['trans_ref'] = strtoupper($transaction->reference_number);
                                    $record['orig_trans'] = strtoupper($transaction->reference_number);
                                    $record['unallocated'] = NULL;
                                    $record['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $record['trans_sub_type'] = NULL;
                                    $record['eff_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $record['invoice_file'] = NULL;
                                    $record['invoice_date'] = NULL;
                                    $record['invoice_no'] = NULL;
                                    $record['invoice_amount'] = NULL;
                                    $record['premium'] = $policy->premium;
                                    $record['other_charges'] = NULL;
                                    $record['due_amount'] = NULL;
                                    $record['pmts_adjust'] = NULL;
                                    $record['due_date'] = NULL;
                                    $record['status'] = 'Paid';
                                    $record['debit'] = str_replace(',', '', number_format($transaction->amount, 2));
                                    $record['credit'] = NULL;
                                    
                                    if ($balance < 0) {
                                        $record['balance'] = -1 * (str_replace(',', '', number_format(($transaction->amount + abs($balance)), 2)));
                                        $balance = -1 * (str_replace(',', '', number_format(($transaction->amount + abs($balance)), 2)));
                                    } else {
                                        $record['balance'] = str_replace(',', '', number_format(($balance - $transaction->amount), 2));
                                        $balance = str_replace(',', '', number_format(($balance - $transaction->amount), 2));
                                    }
                                    
                                    $data[] = $record;
                                    
                                    // SUB-LEDGER
                                    $subRecord = array();
                                    
                                    $subRecord['customer_id'] = $policy->customer_id;
                                    $subRecord['account_id'] = NULL;
                                    $subRecord['policy_id'] = $policy->id;
                                    $subRecord['claim_id'] = NULL;
                                    $subRecord['banking_id'] = NULL;
                                    $subRecord['account_name'] = NULL;
                                    $subRecord['accounting_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $subRecord['trans_type'] = 'Cash Refund';
                                    $subRecord['trans_ref'] = strtoupper($transaction->reference_number);
                                    $subRecord['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $subRecord['credit'] = NULL;
                                    $subRecord['debit'] = str_replace(',', '', number_format($transaction->amount, 2));
                                    
                                    $subData[] = $subRecord;
                                    
                                    $subRecord = array();
                                    
                                    $subRecord['customer_id'] = $policy->customer_id;
                                    $subRecord['account_id'] = NULL;
                                    $subRecord['policy_id'] = $policy->id;
                                    $subRecord['claim_id'] = NULL;
                                    $subRecord['banking_id'] = NULL;
                                    $subRecord['account_name'] = NULL;
                                    $subRecord['accounting_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $subRecord['trans_type'] = 'Cash Refund';
                                    $subRecord['trans_ref'] = strtoupper($transaction->reference_number);
                                    $subRecord['system_date'] = Carbon::parse($transaction->paymentDate)->format('Y-m-d');
                                    $subRecord['credit'] = str_replace(',', '', number_format($transaction->amount, 2));
                                    $subRecord['debit'] = NULL;
                                    
                                    $subData[] = $subRecord;
                                    
                                    $transaction->is_ledger = 1;
                                    $transaction->save();
                                }
                            }
                        }
                    }
                    
                    Ledger::insert($data);
                    SubLedger::insert($subData);
                    $invoicesGenerated++;
                } else {
                    break;
                }
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => "Ledger generated successfully for policy {$policy->policyNumber}. {$invoicesGenerated} invoice(s) created.",
                'invoices_generated' => $invoicesGenerated,
                'policy_number' => $policy->policyNumber
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Error generating ledger: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * API endpoint to generate ledger for a specific policy
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateLedgerAPI($policyId)
    {
       
            
            // Call the ledger generation function
            $result = $this->generateLedgerForPolicy($policyId);
            
            // Log the result
            if ($result['success']) {
                \Log::info("API: Ledger generated successfully for policy ID: {$policyId}", [
                    'invoices_generated' => $result['invoices_generated'],
                    'policy_number' => $result['policy_number']
                ]);
            } else {
                \Log::error("API: Failed to generate ledger for policy ID: {$policyId}", [
                    'error' => $result['message']
                ]);
            }
            
            // Return appropriate HTTP status code
            $statusCode = $result['success'] ? 200 : 400;
            
            return response()->json($result, $statusCode);
            
       
    }
}
