<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Http\Controllers\MobileApp\MobileAppController as MobC;
use AlphaDirect\Agency;
use AlphaDirect\AccountingRules;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractDetails;
use Illuminate\Support\Str;
use PDF;
use Response;
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
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\TbCvgpcLimits;
use AlphaDirect\Models\TbValidOptions;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\Models\PolicyExcessesData;
use AlphaDirect\Models\PolicyBusiExcessesData;
use AlphaDirect\Models\RiskInsurance;
use AlphaDirect\CustomerKycDomCom;
use AlphaDirect\Models\UploadedExcelFile;
use AlphaDirect\Models\CompanyPolicy;

class PolicyFileController extends Controller
{
    public function upload(Request $request)
    {
        set_time_limit(9999);
        $request->validate([
            'file' => 'required|mimes:xlsx,csv|max:16048', 
        ]);

        if ($request->hasFile('file')) {
            
            $file = $request->file('file');
            $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $filePath = 'file/'. now()->format('Y_m_d_H_i_s').'_'.$name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $doc =  new UploadedExcelFile();
            $doc->file_name = $file->getClientOriginalName();
            $doc->file_path = $filePath;
            $doc->uploaded_by = auth()->user()->id ?? null;
            $doc->status = 'pending';
            $doc->remarks = 'Policy Create';
            
            $doc->save();
        }
        return redirect()->route('admin.policy.policyCreateCancelReport')->with('success', 'File uploaded successfully!');
    
    }
    public function excelPoliciesCreate()
    {
        if (auth::user()->hasPermissionTo('bonupolicy_create') || auth()->user()->hasRole('Super Admin')) {
        return view('admin.import.policy_create');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function policyCreateCancelReport(Request $request)
    {
        if (auth::user()->hasPermissionTo('bonupolicy_cancel') || auth()->user()->hasRole('Super Admin')) {
            return view('admin.reports.excelPolicyCreateCancelReport');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
        
    }
    public function policyCreateCancelReportData(Request $request)
    {
        $query = UploadedExcelFile::orderBy('id', 'DESC');


        if ($request->policyStatus_filter != - 1)
        {
            $query->where('remarks', $request->policyStatus_filter);
        }

       if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }

        $policy = $query->get();



        return DataTables::of($policy)->editColumn('created_at', function ($policy)
        {
            return $policy
                ->created_at
                ->diffForHumans();
        })->addColumn('remarks', function ($policy)
        {
           return $policy->remarks;
        })->editColumn('file_path', function ($policy)
        {
            if ($policy->file_path != null)
            {
                $return = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($policy->file_path) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
            }

            else
            {
                $return = '-';
            }



            return $return;
        })->editColumn('report_file', function ($policy)
        {
            if ($policy->report_file != null)
            {
                $return = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($policy->report_file) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
            }else{
                $return = '-';
            }



            return $return;
        })->editColumn('invoice', function ($policy)
        {
            if ($policy->invoice != null)
            {
                $return = '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($policy->invoice) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="download"><i class="flaticon-download"></i></a>';
            }else{
                $return = '-';
            }



            return $return;
        })->editColumn('status', function ($policy)
        {
           return $policy->status;

        })->editColumn('uploaded_by', function ($policy)
        {
           if($policy->uploaded_by != null){
            $user = User::where('id',$policy->uploaded_by)->first(['firstName','lastName']);
            if($user){
                $name = $user->firstName.' '.$user->lastName;
            }else{
                $name = '-';
            }

            return  $name;
           }else{
            return  '-';
           }


        })
            ->rawColumns(['id','status','remarks','invoice','file_path','report_file','uploaded_by'])
            ->make(true);
    }
    public function excelImportPolicyCancellation()
    {
        if (auth::user()->hasPermissionTo('policy-list') || auth()->user()->hasRole('Super Admin')) {
            return view('admin.import.excelPolicyCancellation');
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
       
    }
    public function getExcelImportPolicyCancellation(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv|max:2048', 
        ]);

        if ($request->hasFile('file')) {
            
            $file = $request->file('file');
            $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $filePath = 'file/'. now()->format('Y_m_d_H_i_s').'_'.$name;
            Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
            $doc =  new UploadedExcelFile();
            $doc->file_name = $file->getClientOriginalName();
            $doc->file_path = $filePath;
            $doc->uploaded_by = auth()->user()->id ?? null;
            $doc->status = 'pending';
            $doc->remarks = 'Policy Cancellation';
            $doc->save();
        }
        return redirect()->route('admin.policy.policyCreateCancelReport')->with('success', 'File uploaded successfully!');
    
    }
    public function bonupolicy()
    {
        if (auth::user()->hasPermissionTo('bonupolicy_list') || auth()->user()->hasRole('Super Admin')) {
            $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
            $policies = Policy::all();
            $product_plans = Productplan::where('status', 1)->whereNotIn('id',[14,15])->get();
            $agents = Policy::join('users', 'users.id', 'policies.agent_id')
                ->where('users.active', 1)
                ->where('policies.agent_id', '!=', 'null')
                ->groupBy('policies.agent_id')
                ->get();
            // Show the page
            return view('admin.policy.bonuindex', compact('products', 'policies', 'agents', 'product_plans'));
        } else {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function bonupolicydata(Request $request)
    {
        // dd($request->value_filter);
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
        $totalRecords = CompanyPolicy::select('count(*) as allcount')->count();
        # DB::enableQueryLog();
        // Fetch records
        $records = CompanyPolicy::join('policies', 'company_policies.policyNumber', 'policies.policyNumber')->orderBy('policies.id', 'DESC')
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

        if ($request->product_plan_filter != -1) {
            $records->where('policies.plan_id', $request->product_plan_filter);
        }

        if($request->FilterBy != -1 ){
            if ($request->FilterBy == 'cellphoneFilter' && $request->value_filter) { // filter Policy by cellphone number, policy_cellphone is the ID of the policy
                $records->where('customer.cellphone', 'LIKE', '%'.$request->value_filter.'%');
            }
            if ($request->FilterBy == 'referenceFilter' && $request->value_filter ) { // filter Policy by reference number
                $refPolicyNumber = PaymentTransaction::where('referenceNumber', 'LIKE', '%'.$request->value_filter.'%')->orderBy('id', 'DESC')->pluck( 'policyNumber' )->toArray();
                $records->WhereIn('policies.policyNumber',$refPolicyNumber);
            }
            if ($request->FilterBy == 'vehiclePlateFilter' && $request->value_filter ) { // filter Policy by vehicle plate number
                $vehiclePlateNumber = Vehicle::where('vehiclePlate', 'LIKE', '%'.$request->value_filter.'%')->orderBy('id', 'DESC')->pluck( 'policy_id' )->toArray();
                $records->WhereIn('policies.id',$vehiclePlateNumber);
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
        if (Auth::user()->hasRole('Super Admin') === true) {
            $is_policy_test = [0,1];
        }else{
            $records->where('policies.is_test_policy', 0);
            $is_policy_test = [0];
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
                    'policies.plan_id',
                    'policies.agent_id',
                    'policies.has_vehicle',
                    'policies.has_member',
                    'policies.policyNumber',
                    'policies.status',
                    'policies.preinspection',
                    'policies.is_bundled',
                    'policies.created_at'
                ]
            )->whereNotIn('product_id',[7,8])->whereIn('policies.is_test_policy', $is_policy_test);

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
            if ($record['plan_id'] != null) {
                $plan = Productplan::where('id', $record['plan_id'])->first(array('name'));
                if($plan){
                    $planName = $plan->name;
                }else{
                    $planName = '-';
                }
            } else {
                $planName = 'N/A';
            }

            $productPlanName = $planName;

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
            }
            elseif ($record['status'] == 3) {
                $status .= '<span class="kt-font-bold kt-font-danger">Expired</span>';
            }elseif ($record['status'] == 0) {
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
                if ($trans != null && (strtoupper($trans['status']) == "SUCCESS" || strtoupper($trans['status']) == "SUCCESSFUL" || $trans['status'] == "Paid") ) {
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

                if ($trans != null && (strtoupper($trans['status']) == "SUCCESS" || strtoupper($trans['status']) == "SUCCESSFUL" || $trans['status'] == "Paid")) {
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
            $company = null;
            $Companypolicy = CompanyPolicy::where('policyNumber',$record['policyNumber'])->with('company')->first();
            if($Companypolicy){
                $company = isset($Companypolicy->company) ? $Companypolicy->company->name : null;
            }
            $data_arr[] = array(
                "id"              => $id,
                "view"            => $view,
                "productPlanName" => $productPlanName,
                "name"            => $name,
                "cellphone"       => $cell,
                "product_name"    => $productName,
                "agentName"       => $agentName,
                "status"          => $status,
                "company"         => $company,
                "vehicle_plate"   => ucfirst($vehicle_plate),
                "created_at"      => Carbon::parse($record['created_at'])->format('d-m-Y H:i:s'),
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
}
