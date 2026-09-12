<?php
namespace AlphaDirect\Http\Controllers\Admin;
use AlphaDirect\Http\Controllers\MobileApp\MobileAppController as MobC;
use AlphaDirect\Agency;
use AlphaDirect\AccountingRules;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use Illuminate\Support\Str;
use PDF;
use AlphaDirect\MotorComprehensiveSchedule;
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
use AlphaDirect\Models\PolicyCoverage;
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
use AlphaDirect\PolicyFactor;
use AlphaDirect\PolicyLeads;
use AlphaDirect\PolicyLeadsFactor;
use AlphaDirect\sentPolicyDocuments;
use AlphaDirect\PolicyMember;
use AlphaDirect\PolicyMotorItems;
use AlphaDirect\PolicyPaymentStatusDump;
use AlphaDirect\Product;
use AlphaDirect\Models\ProductCoverage;
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
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Models\PolicyDiscountSurcharge;
use AlphaDirect\Models\SMSEmailLogs;
use Illuminate\Support\Arr;
use AlphaDirect\OtherPartyInsured;
use AlphaDirect\ScheduleTransaction;
use AlphaDirect\Events\CancelTokenEvent;
use AlphaDirect\Events\CancelScheduleTransactionEvent;
use AlphaDirect\Events\ScheduleTransactionEvent;
use AlphaDirect\Events\UpdateRealpayInstallmentDataEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Http\Controllers\admin\DiscountSurchargeController;
use AlphaDirect\Http\Controllers\Admin\RealPayController as AdminRealPayController;
use AlphaDirect\Http\Controllers\DpoPaymentController;
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
use AlphaDirect\RenewPolicyRerateData;
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
use AlphaDirect\Models\LedgerArchive;
use AlphaDirect\Models\PaymentTransactionArchive as PaymentTxArchive;
use AlphaDirect\Models\SubledgerArchive;
use AlphaDirect\PaymentActivityLog;
use AlphaDirect\PolicyPremiumLogs;
use Rap2hpoutre\LaravelLogViewer\Level;
use AlphaDirect\Http\Controllers\NgeniusPaymentController;
use AlphaDirect\Mail\CancelledPolicyMail;
use AlphaDirect\Models\CellphoneDelete;
use AlphaDirect\Models\NgeniusTransection;
use Illuminate\Support\Facades\Artisan;
use AlphaDirect\Models\CustomerKycDelete;
use AlphaDirect\Models\VehicleDelete;
use AlphaDirect\Models\ExpiredPoliciesImportJobs;
use AlphaDirect\Models\PolicyAction;

use AlphaDirect\Models\PolicyCoverageNote;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\SpecifiedCoveragesItems;
use AlphaDirect\Events\NewScheduleTransactionEvent;
use AlphaDirect\Models\PolicyCoverageEntity;
use AlphaDirect\Models\Company;
use AlphaDirect\Http\Controllers\Admin\PDFController;
use AlphaDirect\Models\PolicyCoverageDetail;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;
use AlphaDirect\Models\DocumentDelete;
use AlphaDirect\Models\ValidationRuleGroupMaster;
use AlphaDirect\Models\ValidationRuleGroupMasterDetail;
use AlphaDirect\Models\ValidationRuleMaster;
use AlphaDirect\Models\ValidationRuleDetail;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceGroupCoverage;
use AlphaDirect\Models\PaymentEmail;
use AlphaDirect\Models\PolicyUpgrade;
use AlphaDirect\Models\Extention;
use AlphaDirect\Models\UpdateContract;

use AlphaDirect\PolicySonali;
use AlphaDirect\PaymentTransactionSonali;
use AlphaDirect\Models\PaymentTransactionArchiveSonali;
use AlphaDirect\LedgerSonali;
use AlphaDirect\SubLedgerSonali;
use AlphaDirect\CreditNoteSonali;
use AlphaDirect\Models\LedgerArchiveSonali;

class PolicySonaliController extends Controller
{
    /*
     * Pass data through ajax call
     * for account view tab under policy edit
     * param: policy id ($id)
     * @return mixed
     */

     public function policyViewSonali($id, Request $request)
    {
        
        $fileNames = Lookup::where('key', 'file_type')->get(array('key', 'value'));
        $policy     = PolicySonali::where('id', $id)->first();
        $banks      = Banks::all();
        $PolicyLedgers = LedgerSonali::orderBy('id', 'DESC')->where('policy_id', $policy->id)->first();
        
        $policyactivatecancelleddates = PolicyActivateCancelledDate::where('policyNumber', $policy->policyNumber)->orderBy('id','desc')->first('cancelled_date');

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
        $policyCover = null;
        //PolicyCoverage::where('policy_id', $policy->id)->get(array('id', 'main', 'coverage_value', 'discount', 'type', 'value'));
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
                    $PolicyCoverages = null;// PolicyCoverage::where('vehicle_id', $vehicles->id)->where('policy_id', $policy->id)->where('term_id', $Terms_id->id)->orderBy('id','desc')->get();
                    else
                    $PolicyCoverages = null;// PolicyCoverage::where('vehicle_id', $vehicles->id)->where('policy_id', $policy->id)->orderBy('id','desc')->get();

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


            $emailDocs = Documents::whereIn('id', $docsid)->where('status', 1)->get(array('name', 'link','status','product_id'));
            }else{
                $emailDocs = DB::select(DB::raw('SELECT * FROM documents where status = 1 and (product_id = '. $policy->product_id .' || product_id = -1)'));
            }



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
            $balance_arrears = LedgerSonali::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(array('balance'));
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
            if($make){
               $device->models = DeviceMakeModel::where('make_id', $make->id)->get(array('id', 'name'))->toArray();
            }else{
            $device->models = [];
            }

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

        /*$ledgerBal = LedgerSonali::where('policy_id', $policy->id)
        ->orderBy('id', 'DESC')
        ->sum('premium');

        $ledgerBalArc = LedgerArchiveSonali::where('policy_id', $policy->id)
        ->orderBy('id', 'DESC')
        ->sum('premium');
        $totalBal = $ledgerBal + $ledgerBalArc;
        dd($totalBal);*/

        $balance = LedgerSonali::where('policy_id', $policy->id)->orderBy('system_date', 'desc')->first(array('balance'));
        if ($balance != null) {
            $balance = $balance->balance;
        } else {
            $balance = LedgerArchiveSonali::where('policy_id', $policy->id)->orderBy('system_date', 'desc')->first(array('balance'));
            if ($balance != null) {
                $balance = $balance->balance;
            } else {
                $balance = 0;
            }
        }
        $totalBal = $balance;
        
        /*$paymentTrans = PaymentTransactionSonali::where('policy_id', $policy->id)
        ->where('status','LIKE',"%success%")
        ->orderBy('id', 'DESC')
        ->sum('amount');

        $paymentTransArch = PaymentTransactionArchiveSonali::where('policy_id', $policy->id)
        ->where('status','LIKE',"%success%")
        ->orderBy('id', 'DESC')
        ->sum('amount');
        $transactionTotal = $paymentTrans + $paymentTransArch;*/
        $balance = $totalBal;
        $motorPreminum = NULL;
        $newConvertedAmt = NULL;

        if ($policy->product_id == 3 && $policy->premium) {  
            $motorPreminum = $this->getMotorComprehensivePremium($policy->policyNumber);
            // dd($motorPreminum);

            $txLog = PaymentTransactionSonali::where('policyNumber', $policy->policyNumber)
                ->where('status','!=','CANCELLED')
                ->where('status','!=','FAILED')
                ->orderBy('id', 'DESC')->get();
            if ($txLog == null){
                $txLog = PaymentTransactionSonali::where('policyNumber', $policy->policyNumber)
                ->where('status','!=','CANCELLED')
                ->where('status','!=','FAILED')
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

        if($policy->billingStartDate != null){
            $pos = strpos($policy->billingStartDate, '/');
            if ($pos !== false) {
                $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
            } else {
                $policy->billingStartDate = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
            }
        }

        if($user->profile->salary_pay_date != null){
            $pos = strpos($user->profile->salary_pay_date, '/');
            if ($pos !== false) {
                $user->profile->salary_pay_date = Carbon::createFromFormat('d/m/Y', $user->profile->salary_pay_date)->format('d-m-Y');
            } else {
                $user->profile->salary_pay_date = Carbon::parse($user->profile->salary_pay_date)->format('d-m-Y');
            }
        }

        $policyRenewalButton = PolicyRenewal::where('policyNumber',$policy->policyNumber)->where('renew_completed',0)->orderBy('id','desc')->first();
        // dd($policyRenewalButton);
        $getTerms = PolicyTerm::where('policy_id',$policy->id)->whereIn('trans_type',['RENEW','NEW BUSINESS'])->orderBy('id','desc')->first();
        if(isset($getTerms)){
            $activatedDatePolicy = Carbon::parse($getTerms->term_end_date)->format('Y-m-d');
            $activatedDiffDays =  \Carbon\Carbon::createFromTimeStamp(strtotime($activatedDatePolicy))->diffInDays();

            $getToday =\Carbon\Carbon::now()->format('Y-m-d');
            if ($activatedDiffDays <= 60 || $getTerms->term_end_date <= $getToday) {
                $activatedDiffDays = 1;
            } else {
                $activatedDiffDays = null;
            }
        }else{
            $activatedDiffDays = null;
        }

        if($policy->status == 2){
            if(isset($policyactivatecancelleddates->cancelled_date)){
                // $cancelEndDate = Carbon::parse(\Carbon\Carbon::createFromFormat('m-d','07-01'))->year(now()->format('Y'))->format('Y-m-d'); //"2022-07-01"
                // $cancelDate = Carbon::parse($policyactivatecancelleddates->cancelled_date)->format('Y-m-d');
                // if($cancelEndDate >= $cancelDate ){
                //     $checkCancelDate = false;
                // }else{
                //     $checkCancelDate = true;
                // }

                $cancelDate = Carbon::parse($policyactivatecancelleddates->cancelled_date)->format('Y-m-d');
                $checkCancelDate =  \Carbon\Carbon::createFromTimeStamp(strtotime($cancelDate))->diffInDays();

                if ($checkCancelDate > 45) {
                    $checkCancelDate = null;
                }

            }else {
                $checkCancelDate = null;
            }
        }else {
            $checkCancelDate = null;
        }

        // $paymentDetails = PaymentTransaction::where('policyNumber',$policy->policyNumber)->get(array('id','referenceNumber','amount','paymentDate'));

        $paytrx_graphite_tx = PaymentTransaction::where('policyNumber',$policy->policyNumber)->where('status','!=','CANCELLED')->get();
        $paytrx_archive_tx = PaymentTxArchive::where('policyNumber',$policy->policyNumber)->where('status','!=','CANCELLED')->get();
        $merged_all = $paytrx_archive_tx->merge($paytrx_graphite_tx);
        $paymentDetails = $merged_all->all();

        $getContracts = null;
        if ($banking && $banking->billing != null && $banking->billing == 'RealPay') {
            $request['clientNumber'] = $policy->policyNumber;
            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
            $getcontractData = $realpay->getContractInfoForMotorComp($request);
            // if ($policy->product_id == 3) {
                if (!isset($getcontractData)) {
                    $realpayContract = $realpay->getContractInfo($request);
                } else{
                    $realpayContract = $realpay->getContractInfoForInstantProduct($request);
                }
            // } else {
            //     $realpayContract = $realpay->getContractInfoForInstantProduct($request);
            // }

            // if (isset($realpayContract)) {
            //     if ($realpayContract->getData()->Status == 'Success') {
            //         $getContracts = $realpayContract->getData()->contracts;
            //         $getContracts = end($getContracts);
            //     } else {
            //         $getContracts = null;
            //     }
            // } else {
            //     $getContracts = null;
            // }
            $getContracts = null;
        }

        $autoRenewedPolicy = 0;
        $expired_policies_import = ExpiredPoliciesImportJobs::where('policyNumber',$policy->policyNumber)->where('is_renewed',0)->where('renew_completed',0)->where('can_expired',0)->whereDate('expiry_date','<=',\Carbon\Carbon::now())->first();
        if ($expired_policies_import && $expired_policies_import->new_premium <= $expired_policies_import->old_premium) {
            if ($expired_policies_import->paymentMethod == 'DPO' || $expired_policies_import->paymentMethod == 'VCS' || $expired_policies_import->paymentMethod == 'RealPay' || $expired_policies_import->paymentMethod == 'Realpay') {

                $payTrx = PaymentTxArchive::where('policyNumber', $expired_policies_import->policyNumber)->whereBetween('paymentDate', [Carbon::now()->subMonth(3), Carbon::now()])->whereIn('status',['Success','SUCCESS','success','1'])->first();
                if (!isset($payTrx)) {
                    $payTrx = PaymentTransaction::where('policyNumber', $expired_policies_import->policyNumber)->whereBetween('paymentDate', [Carbon::now()->subMonth(3), Carbon::now()])->whereIn('status',['Success','SUCCESS','success','1'])->first();
                }

                if (isset($payTrx)) {
                    $autoRenewedPolicy = 1;
                }
            }
        }

        $renewPolicyManually = ExpiredPoliciesImportJobs::where('policyNumber',$policy->policyNumber)->where('can_autoRenewed',0)->first();

        $policyRenewalCheck = PolicyRenewal::where('policyNumber',$policy->policyNumber)->where('is_renewed',0)->first();

        $currentDate = Carbon::now()->format('Y-m-d');
        $termData = PolicyTerm::where('policy_id',$policy->id)->whereDate('term_end_date','>', $currentDate)->whereIn('trans_type',['RENEW','NEW BUSINESS'])->orderBy('id','desc')->get();

        $policyCover = null;
        $pay_email = PaymentEmail::where('policyNumber',$policy->policyNumber)->first();
        if($pay_email){
           $pay_email =  $pay_email;
        }else{
            $pay_email = null;
        }
        $policyUpgrade = PolicyUpgrade::where('policy_id',$policy->id)->orderBy('id','desc')->first();
        if(isset($policyUpgrade) && $policyUpgrade != null){
            $policyUpgrade  = $policyUpgrade;
         }else{
            $policyUpgrade = null;
         }
       
        $noOfClaimCount = Claim::where('customer_id',$policy->customer_id)->whereNotIn('claim_type',['Glass','Key Loss'])->count();
        if (($policy->agent_id == auth()->user()->id) || $policy->agent_id == null || $logged_in_agency_manager == true|| auth::user()->hasPermissionTo('policy-Full List') || $policy->agent_id == '0') {
            if ($product->has_vehicle == 1) {
                
                return view('admin.policysonali.policyDetails_View', 
                compact('noOfClaimCount','policyUpgrade','pay_email','termData',
                'policyRenewalCheck','renewPolicyManually','autoRenewedPolicy',
                'getContracts','newConvertedAmt','paymentDetails','reratelogData',
                'scheduleTransactionCount','balanceArrears','EmailDocsPolicyBundled',
                'policyRenewalButton','activatedDiffDays','checkCancelDate',
                'vehiclePolicyTyreRim','vehicleDetailsTyreRim','vehiclePurposeTyreRim',
                'balance_fresh','policies_reinstate','balance_arrears',
                'policyactivatecancelleddates','PolicyCoverages','annual_Premium',
                'PolicyBundled','PolicyLedgers','three_Installment','monthly_Premium',
                'years','policy_term','is_renewal','omang_passport_id','motorPreminum',
                'quote_id','fileNames','feedback','coverNote', 'balance', 'cancelNote', 'dataModel',
                 'dataMake', 'reratedPremiumQuotes', 'premiumCalcDetails', 'passpostIssueCountry', 
                 'storeName', 'emailDocs', 'vehicleMakes', 'vehicleModels', 'claims', 'productPlan', 
                 'premium', 'count','policyFactors', 'vehicle_purpose', 'policyCover', 'policy', 'user', 
                 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 
                 'agent_name', 'policyMotorItems', 'motor_items', 'transaction', 'policy_cellphone', 'updateDevices', 
                 'banks','customerMati','matiDetails','matiVerifData','vehicleOld','old_vehicle_data_reinstant',
                 'reinstate_days','days_to_reinstate','functionality'));
            } else {
                
                return view('admin.policysonali.policyDetails_View', compact('noOfClaimCount','policyUpgrade','pay_email','termData','policyRenewalCheck','renewPolicyManually','autoRenewedPolicy','getContracts','newConvertedAmt','paymentDetails','reratelogData','scheduleTransactionCount','balanceArrears','EmailDocsPolicyBundled','policyRenewalButton','activatedDiffDays','checkCancelDate','vehiclePolicyTyreRim','vehicleDetailsTyreRim','vehiclePurposeTyreRim','balance_fresh','policies_reinstate','balance_arrears','policyactivatecancelleddates','PolicyCoverages','annual_Premium','PolicyLedgers','PolicyBundled','three_Installment','monthly_Premium','years','policy_term','is_renewal','omang_passport_id','motorPreminum','quote_id','fileNames','feedback','coverNote','balance', 'cancelNote', 'passpostIssueCountry', 'storeName', 'emailDocs', 'claims', 'productPlan', 'premium', 'count','policyFactors', 'vehicle_purpose', 'policyCover', 'policy', 'user', 'product', 'productFactors', 'members', 'beneficiaries', 'banking', 'kyc', 'vehicle', 'agents', 'agent_name', 'policyMotorItems', 'motor_items', 'transaction', 'policy_cellphone', 'updateDevices', 'banks','customerMati','matiDetails','matiVerifData','vehicleOld','old_vehicle_data_reinstant','reinstate_days','days_to_reinstate','functionality'));
            }
        } else {
            return Redirect()->back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
     public function isJSON($string){
        return is_string($string) && is_array(json_decode($string, true))  && (json_last_error() == JSON_ERROR_NONE) ? true : false;
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
    public function getMonthlyPrem($existingPremFreq,$existingPremium){
        if($existingPremFreq == 2){
            return ($existingPremium / 4 ) * 1.08;
        }
        if($existingPremFreq == 3){
            return ($existingPremium / 12 ) * 1.08;
        }
    }
    public function ledgerAccountViewSonali($id)
    {
        // $ledgerData = LedgerSonali::where('policy_id', $id)
        // ->where(function ($query) {
        //     $query->whereNotNull('invoice_file')
        //     ->orWhereNotNull('credit')
        //     ->orWhere('trans_type', 'Refund')
        //     ->orWhere('trans_type', 'Credit Note');
        // })->get();

        $ledgerData = LedgerSonali::where('policy_id', $id)
        ->where(function ($query) {
            $query->Where('trans_type', 'Invoices')
            ->orWhere('trans_type', 'Payment');
        })->get();
        
        $ledgerArchive = LedgerArchiveSonali::where('policy_id', $id)
        ->where(function ($query) {
            $query->Where('trans_type', 'Invoices')
            ->orWhere('trans_type', 'Payment');
        })->get();
       
        $merged = $ledgerArchive->merge($ledgerData);
        
        $ledger = $merged->all();

        
        // $ledger = Ledger::with('policy.customer')->where('policy_id', $id)
        //     ->where(function ($query) {
        //         $query->whereNotNull('invoice_file')->orWhereNotNull('credit')->orWhere('trans_type', 'Refund');
        //     })->get();

        return DataTables::of($ledger)
            ->addColumn('customer_name', function ($ledger) {
                $customer = Customer::where('id',$ledger->customer_id)->first(array('firstName','lastName'));

                if($customer && $customer->firstName != null){
                    $customer_name = $customer['firstName'];
                }else{
                    $customer_name = null;
                }

                if($customer && $customer->lastName != null){
                    $customer_lastname = $customer['lastName'];
                }else{
                    $customer_lastname = null;
                }

                return  $customer_name . ' ' .  $customer_lastname;
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

    public function recievableDataSonali($id)
    {
        $ledger_graphite = LedgerSonali::where('policy_id', $id)->whereNotNull('credit')->get();
        $ledger_archive = LedgerArchive::where('policy_id', $id)->whereNotNull('credit')->get();
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
    public function invoicingDataSonali($id)
    {
        $ledger_graphite = LedgerSonali::where('policy_id', $id)->where('trans_type', 'Invoices')->get();
        $ledger_archive = LedgerArchiveSonali::where('policy_id', $id)->where('trans_type', 'Invoices')->get();
        $merged = $ledger_archive->merge($ledger_graphite);
        $ledger = $merged->all();

        return DataTables::of($ledger)
            ->editColumn('invoice_no', function ($ledger) {
                if ($ledger->invoice_no != null) {
                    $cred = '<a href="' . route('admin.getInvoiceSonali', $ledger->id) . '" target="_blank"> ' . $ledger->invoice_no . '</a>';
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
                    //     $cred = '<span class="kt-font-bold kt-font-primary">- P' . number_format(
                    //abs($ledger->balance), 2, '.', '') . '</span>';
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
            //     $actions = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($ledger->invoice_file) . '" target="_blank" download class= "btn btn-sm btn-clean btn-icon btn-icon-md" title="Download Invoice">
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
                $actions = '<a href="'. url('admin/creditNoteViewSonali/'.$ledger->id) .'" target="_blank"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Creditnote">
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
    public function subLedgerDataSonali($id)
    {
        $subledger_graphite = SubLedgerSonali::where('policy_id', $id)->get();
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

    public function getInvoiceSonali($id)
    {

        try {
            $ledger = LedgerSonali::where('id', $id)->first(array('id', 'policy_id', 'customer_id', 'invoice_no'));

            if($ledger == NULL)
                $ledger = LedgerArchive::where('id', $id)->first(array('id', 'policy_id', 'customer_id', 'invoice_no'));
            if ($ledger != null) {
                $exists = Storage::disk('s3')->exists('MIS/' . $ledger->policy_id . '/' . 'Customer/' . $ledger->customer_id . '/' . 'Invoice/' . $ledger->invoice_no . '.pdf');
                //                if ($exists == true) {
                //                    return Redirect::to(Helper::getCloudFrontURL('MIS/' . $ledger->policy_id . '/' . 'Customer/' . $ledger->customer_id . '/' . 'Invoice/' . $ledger->invoice_no . '.pdf'));
                //                } else {
                                $ids = array();
                                array_push($ids, $id);
                                $path = Helper::generateInvoiceSonali($ids);
                                //return Redirect::to(Helper::getCloudFrontURL($path));
                //                }
            }
        } catch (Exception $e) {
        }
    }
    public function creditNoteView($id)
    {
        $ledger = LedgerSonali::where('id', $id)->first(array('id','policy_id','invoice_date', 'invoice_amount'));
        if($ledger == NULL)
            $ledger = LedgerArchive::where('id', $id)->first(array('id','policy_id','invoice_date', 'invoice_amount'));

        $invoice_amount = $ledger->invoice_amount;
        if($ledger && $ledger->policy_id != null){
            $policy = PolicySonali::where('id', $ledger->policy_id)->first(array('id','policyNumber','policyActivatedDate','premium','premium_freq'));
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
        return view('admin.policysonali.creditNote', compact('credit_note','ledger','one_day_premium','policy','unearned_premium','earned_premium','no_of_active_days','policy_cancelled_date','policy_activated_date','invoice_amount'));
    }
    public function creditNoteStatementSonali(Request $request,$id,$ledger)
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
        $invoice          = LedgerSonali::where('id', $ledger)->first(array('id','invoice_no'));
        if($invoice == NULL)
            $invoice = LedgerArchive::where('id', $ledger)->first(array('id','invoice_no'));
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
        $path = 'CreditNote/'.$credit_note_no .'.pdf';

        libxml_use_internal_errors(true);
        $pdf = PDF::loadView('admin.notes.credit_note_statement_new',$array);
        /*Storage::disk('s3')->put($path, $pdf->output(), 'public');*/

        $credit_note = new CreditNoteSonali();
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

        $ledger            = LedgerSonali::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
        if($ledger == NULL)
            $ledger = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();

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
            $record['balance'] = str_replace(',', '',number_format(($amount - abs($balance)), 2));
            $balance = str_replace(',', '',number_format(($amount - abs($balance)), 2));
        } else {
            $record['balance'] = str_replace(',', '',number_format(($balance + $amount), 2));
            $balance = str_replace(',', '',number_format(($balance + $amount), 2));
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
       // dd($record);
        LedgerSonali::insert($record);
        SubLedgerSonali::insert($subData);
        return redirect()->back()->with('success', 'Credit note generated Successfully');
    }

   
}

