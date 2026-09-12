<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\AccidentInjury;
use AlphaDirect\Activation;
use AlphaDirect\Branch;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimCellphone;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\ClaimReserves;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\Country;
use AlphaDirect\Coverage;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Exports\AgentReportExport;
use AlphaDirect\Exports\AnniversaryDateReprotExport;
use AlphaDirect\Exports\BookOfBusinessReportExport;
use AlphaDirect\Exports\ClaimAsOnDateDataExport;
use AlphaDirect\Exports\ClaimBordereauxExport;
use AlphaDirect\Exports\ClaimBordereauxOutstandingPaymentExport;
use AlphaDirect\Exports\ReinsuranceRiskProfilesSummaryExport;
use AlphaDirect\Exports\ClaimPaymentBordereauxExport;
use AlphaDirect\Exports\ClaimPaymentAsOnDateDataExport;
use AlphaDirect\Exports\DetailedAnalysisReportExcel;
use AlphaDirect\Exports\InforceReportExport;
use AlphaDirect\Exports\PolicyStatusReportExport;
use AlphaDirect\Exports\EarnedpremiumwithExport;
use AlphaDirect\Exports\RealpayTransactions;
use AlphaDirect\Exports\ReInsuranceClaimsprofileExport;
use AlphaDirect\Exports\SubLedgerReportExport;
use AlphaDirect\Exports\SummeryAgeAnalysisReport;
use AlphaDirect\Exports\TransactionSummaryProductReportExport;
use AlphaDirect\Exports\ClaimCoverageAllocationExport;
use AlphaDirect\Exports\ReInsuranceRiskProfileExport;
use AlphaDirect\Exports\ClaimOutstandingAgeingReportExport;
use AlphaDirect\Exports\WrittenPremiumReportExport;
use AlphaDirect\Exports\TransactionReportByProductExcel;
use AlphaDirect\Ledger;
//use AlphaDirect\Exports\TransactionReportByBooking;
use AlphaDirect\Exports\TransactionReportByBookingDateExport;
use AlphaDirect\Exports\TransactionReportProductExcel;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Lookup;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\PaymentSchedule;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyActivateCancelledDate;
use AlphaDirect\PolicyCellPhone;
use AlphaDirect\PolicyReportView;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\State;
use AlphaDirect\Stores;
use AlphaDirect\SummaryAgeAnalysisReport;
use AlphaDirect\Transactionsubtype;
use AlphaDirect\UserProfile;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\Vehicle;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\VcsTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Auth;
use PDF;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables as FacadesDataTables;
use AlphaDirect\Models\CronStatus;

class ReportController extends Controller
{
    public function index()
    {
        if (Auth::user()->hasPermissionTo('policy-report-list'))
        {
            $products = Product::with('type')->where('status', 1)
                ->get(array(
                    'id',
                    'product_type_id',
                    'name',
                    'is_motor_items',
                    'kyc_customer',
                    'has_vehicle',
                    'has_subApplicant'
                ));
            $productPlans = Productplan::where('status', 1)->get(array(
                'id',
                'name'
            ));
            $customerNumbers = Customer::where('id', '!=', null)->get();
            $policyAgents = Policy::with('user')->where('agent_id', '!=', 'null')
                ->groupBy('agent_id')
                ->get();

            //  return response($policyLeadAgent);
            return view('admin.reports.index', compact('products', 'productPlans', 'customerNumbers', 'policyAgents'));
        }   
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    public function data(Request $request)
    {
        $query = Policy::orderBy('created_at', 'DESC');
        if ($request->product_plan != - 1)
        {
            $query->where('plan_id', $request->product_plan);
        }
        if ($request->product_filter != - 1)
        {
            $query->where('product_id', $request->product_filter);
        }
        if ($request->policyStatus_filter != - 1)
        {
            $query->where('status', $request->policyStatus_filter);
        }
        if ($request->policy_number != - 1)
        {
            $query->where('policyNumber', $request->policy_number);
        }
        if ($request->contact_number != - 1)
        {
            $customerId = Customer::where('cellphone', $request->contact_number)
                ->first('id');
            $query->where('customer_id', $customerId->id);
        }
        if ($request->premium_value != - 1)
        {
            $query->where('premium', $request->premium_value);
        }
        if ($request->customer_name != - 1)
        {
            $customer = Customer::where('firstName', $request->customer_name)
                ->orWhere('lastName', $request->customer_name)
                ->first(array(
                    'id',
                    'firstName',
                    'lastName'
                ));
            $query->where('customer_id', $customer->id);
        }
        if ($request->payment_status != - 1)
        {
            $paymentStatus = Transaction::where('status', 'like', '%' . $request->payment_status . '%')
                ->first();
            $query->where('customer_id', $paymentStatus->customer_id);
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }

        $policy = $query->get(['id', 'customer_id', 'plan_id', 'product_id', 'has_vehicle', 'has_member', 'policyNumber', 'status', 'created_at']);

        /* if ($request->policyStatus_filter != -1 && $request->product_filter != -1 && $request->policy_number != -1) {
        $policy = Policy::where('status', $request->policyStatus_filter)->where('product_id', $request->product_filter)->where('plan_id', $request->product_plan)->get(['id', 'customer_id', 'product_id', 'has_vehicle', 'has_member', 'policyNumber', 'status', 'created_at']);
        }*/

        return DataTables::of($policy)->editColumn('created_at', function ($policy)
        {
            return $policy
                ->created_at
                ->diffForHumans();
        })->editColumn('status', function ($policy)
        {
            if ($policy->status == 1)
            {
                $return = '<span class="kt-font-bold kt-font-brand">Activated</span>';
            }
            elseif ($policy->status == 2)
            {
                $return = '<span class="kt-font-bold kt-font-danger">Cancel</span>';
            }
            else
            {
                $return = '<span class="kt-font-bold kt-font-focus">Deactivated</span>';
            }

            if ($policy->kyc && $policy
                    ->kyc->compliance == 1)
            {
                $return .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            }
            else
            {
                $return .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            }

            return $return;
        })->addColumn('name', function ($policy)
        {
            if ($policy->customer != null)
            {
                if($policy->customer->firstName != null && $policy->customer->lastName != null){
                    return $policy
                            ->customer->firstName . ' ' . $policy->customer->lastName;
                }elseif($policy->customer->firstName == null && $policy->customer->lastName != null){
                    return 'N/A (first name)' . ' ' . $policy->customer->lastName;
                }elseif($policy->customer->firstName != null && $policy->customer->lastName == null){
                    return $policy->customer->firstName . ' ' . 'N/A';
                }else{
                    return 'N/A';
                }
            }

        })->addColumn('product_name', function ($policy)
        {
            if ($policy->product != null)
            {
                if($policy->product->name != null){
                    return $policy->product->name;
                }else{
                    return 'N/A';
                }
            }

        })

            ->rawColumns(['status'])
            ->make(true);
    }
    public function CronReport()
    {
        if (Auth::user()->hasPermissionTo('policy-report-list'))
        {
            $products = Product::with('type')->where('status', 1)
                ->get(array(
                    'id',
                    'product_type_id',
                    'name',
                    'is_motor_items',
                    'kyc_customer',
                    'has_vehicle',
                    'has_subApplicant'
                ));
            $productPlans = Productplan::where('status', 1)->get(array(
                'id',
                'name'
            ));
            $customerNumbers = Customer::where('id', '!=', null)->get();
            $policyAgents = Policy::with('user')->where('agent_id', '!=', 'null')
                ->groupBy('agent_id')
                ->get();

            //  return response($policyLeadAgent);
            return view('admin.reports.cron', compact('products', 'productPlans', 'customerNumbers', 'policyAgents'));
        }   
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    public function CronReportdata(Request $request)
    {
       
        if(isset($request->filterDateFrom) && $request->filterDateFrom != null){
            $starttime =Carbon::parse($request->filterDateFrom)->format('Y-m-d');
            $endtime= Carbon::parse($request->filterDateFrom)->format('Y-m-d') . ' 23:59:59';
        }else{
            $starttime = Carbon::parse('today')->format('Y-m-d');
            $endtime= Carbon::parse('today')->format('Y-m-d') . ' 23:59:59';
        }
       
      
        
        $cron = CronStatus::whereBetween('created_at' , [ $starttime, $endtime ])->get();
      
        return DataTables::of($cron)
           
            ->editColumn('start', function ($cron)
            {
                if ($cron->start != null) {
                   
                    return Carbon::parse($cron->start)->format('H:i:s d-m-Y');
                }else{
                    return "-";
                }
            })
            ->editColumn('end', function ($cron)
            {
                if ($cron->end != null) {
                   
                    return Carbon::parse($cron->end)->format('H:i:s d-m-Y');
                }else{
                    return "-";
                }
            })
            ->editColumn('mail_status', function ($cron)
            {
                if ($cron->mail_send == 1) {
                   
                    return "Yes";
                }else{
                    return "-";
                }
            })
            ->editColumn('created_at', function ($cron)
            {
                if ($cron->created_at != null) {
                   
                    return Carbon::parse($cron->created_at)->format('h:i:s d-m-Y');
                }else{
                    return "-";
                }
            })

            ->rawColumns(['mail_status','start','end','created_at'])
            ->make(true);

    }


    public function show($id){
        return $id;
    }

    public function allPolicyData(Request $request)
    {
        $query = Policy::query()->orderBy('id','DESC');


        if ($request->agent_id != '-1')
        {
            $query->where('agent_id', $request->agent_id);
        }
        $policy = $query;
        return DataTables::eloquent($policy)
            ->filter(function ($query) {

                //filter by product plan
                if (request('product_plan') != - 1)
                {
                    $query->where('plan_id', request('product_plan'));
                }

                //filter by product filter
                if (request('product_filter') != - 1)
                {
                    $query->where('product_id', request('product_filter'));
                }

                //filter by policy status filter
                if (request('policyStatus_filter') != - 1)
                {
                    $query->where('status', request('policyStatus_filter'));
                }

                if (request('policy_number') != - 1)
                {
                    $query->where('policyNumber', request('policy_number'));
                }

                //filter by contact number
                if (request('contact_number') != - 1)
                {

                    $customerId = Customer::where('cellphone', 'like', '%' . request('contact_number') . '%')
                        ->first('id');

                    $query->where('customer_id', $customerId->id);

                }

                //filter by premium

                if (request('premium_value') != - 1)
                {
                    $query->where('premium', request('premium_value'));
                }

                //filter by customer name
                if (request('customer_name') != - 1)
                {
                    $customer = Customer::where('firstName', 'like', '%' . request('customer_name') . '%')
                        ->orWhere('lastName', 'like', '%' . request('customer_name') . '%')
                        ->pluck('id');
                    $query->whereIn('customer_id', $customer);
                }

                //filter by sate
                if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
                {
                    $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                        ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                        ->format('Y-m-d') ]);
                }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
                    //Carbon::parse('today')
                    $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                        ->format('Y-m-d') , Carbon::parse('today')
                        ->format('Y-m-d') ]);
                }
                //filter by payment status
                if(request('payment_status') !='-1'){
                    if(request('payment_status') == 'FAILED'){
                        $paymentStatus = Transaction::where('status', 'like','%'.'FAILED'.'%')
                            ->orWhere('status', 'like','%'.'F'.'%')
                            ->whereNotNull('status')
                            ->latest()
                            ->groupBy('policyNumber')
                            ->pluck('policyNumber');
                    }
                    if(request('payment_status') == 'SUCCESS'){
                        $paymentStatus = Transaction::where('status', 'like','%'.'SUCCESS'.'%')
                            ->orWhere('status', 'like','%'.'S'.'%')
                            ->whereNotNull('status')
                            ->groupBy('policyNumber')
                            ->latest()
                            ->pluck('policyNumber');
                    }
                    if(request('payment_status') == 'PENDING'){
                        $paymentStatus = Transaction::where('status', 'A')
                            ->whereNotNull('status')
                            ->groupBy('policyNumber')
                            ->latest()
                            ->pluck('policyNumber');
                    }



                    if(count($paymentStatus)>0){
                        $query->whereIn('policyNumber',$paymentStatus);
                    }
                }


            })
            ->editColumn('created_at', function ($policy)
            {
                return $policy->created_at;
            })->editColumn('status', function ($policy)
            {
                if ($policy->status == 1)
                {
                    $return = 'Activated';
                }
                elseif ($policy->status == 2)
                {
                    $return = 'Cancel';
                }
                else
                {
                    $return = 'Deactivated';
                }

                return $return;
            })->editColumn('agent_id', function ($policy)
            {
                if ($policy->agent_id != null)
                {
                    $user = User::where('id', $policy->agent_id)
                        ->first(array(
                            'firstName',
                            'lastName'
                        ));
                    if ($user != null)
                    {
                        $return = $user->firstName . ' ' . $user->lastName;
                    }
                    else
                    {
                        $return = 'N/A';
                    }
                }
                else
                {
                    $return = 'N/A';
                }

                return $return;
            })->addColumn('name', function ($policy)
            {
                if ($policy->customer != null)
                {
                    if($policy->customer->firstName != null && $policy->customer->lastName != null){
                        return $policy
                            ->customer->firstName . ' ' . $policy->customer->lastName;
                    }elseif($policy->customer->firstName == null && $policy->customer->lastName != null){
                        return 'N/A (first name)' . ' ' . $policy->customer->lastName;
                    }elseif($policy->customer->firstName != null && $policy->customer->lastName == null){
                        return $policy->customer->firstName . ' ' . 'N/A';
                    }else{
                        return 'N/A';
                    }
                }
                else
                {
                    return '-';
                }
            })->addColumn('cellphone', function ($policy)
            {
                if ($policy->customer_id != null)
                {
                    $customerNumber = Customer::where('id', $policy->customer_id)
                        ->first('cellphone');
                    if($customerNumber)
                        return $customerNumber->cellphone;
                    else
                        return 'N/A';

                }
                else
                {
                    return '-';
                }

            })->addColumn('product_name', function ($policy)
            {
                if ($policy->product_id != null)
                {
                    return $policy
                        ->product->name;
                }
                else
                {
                    return '-';
                }

            })
            ->addColumn('payment_method', function ($policy)
            {
                $banking = CustomerBanking::where('policy_id', $policy->id)->first(array('billing'));
                if ($policy->customer_id != null) {
                    $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first();
                }
                if ($trans && $trans->paymentMethod != null) {
                    if($trans->paymentMethod == 'orangeMoney')
                        $payment = 'Orange USSD';
                    else
                        $payment = $trans->paymentMethod;

                } else {
                    $data = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status','orangeTransaction_id'));

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
                            if($banking->billing == 'orangeMoney')
                                $payment = 'Orange USSD';
                            else
                                $payment = $banking->billing;
                        else
                            $payment = 'Payment method not found';
                    }
                }
                return $payment;

            })
            ->addColumn('plan_id', function ($policy)
            {
                /* dd($policy->plan_id );*/
                if ($policy->plan_id != null)
                {
                    $productPlan = Productplan::where('id', $policy->plan_id)
                        ->first('name');
                    if($productPlan->name)
                        return $productPlan->name;
                    else
                        return 'N/A';
                }
                else
                {
                    return '-';
                }

            }) ->addColumn('vehicle_plate', function ($policy)
            {
                if ($policy->has_vehicle == 1)
                {
                    $vehicle = Vehicle::where('policy_id', $policy->id)->first('vehiclePlate');
                    if($vehicle){
                        if($vehicle->vehiclePlate)
                            return strtoupper($vehicle->vehiclePlate);
                        else
                            return 'N/A';
                    }else{
                        return 'N/A';
                    }
                }
                else
                {
                    return '-';
                }

            })
            ->addColumn('branch', function ($policy)
            {
                //return 'na';
                $activationCode_branch = Activation::where('activation_code', $policy->activation_code)
                    ->first();
                if($activationCode_branch){
                    $branch = Branch::where('id', $activationCode_branch->branch)
                        ->first();
                    $source = Policy::where('activation_code', $policy->activation_code)
                        ->first('leadSource');
                    if($branch){
                        return $branch->name;
                    }else{
                        return 'N/A';
                    }
                }else{
                    return 'N/A';
                }


            })->editColumn('premium', function ($policy)
            {
                if ($policy->premium != null)
                {
                    $premium = 'P' . $policy->premium;
                    return $premium;
                }
                else
                {
                    return '-';
                }

            })->addColumn('paymentStatus', function ($policy)
            {
                $trans = PaymentTransaction::where('policyNumber', $policy->customer_id)->orderBy('id', 'DESC')->first();
                if ($trans != null) {
                    if ($trans != null && strtoupper($trans->status) == "SUCCESS") {
                        $status = "Successful";
                    } elseif ($trans != null && (strtoupper($trans->status) == "PENDING" || strtoupper($trans->status) == "A")) {
                        $status = "Pending";
                    } elseif ($trans != null && strtoupper($trans->status) == "PROCESSING") {
                        $status = "Processing";
                    } elseif ($trans != null && strtoupper($trans->status) == "CANCELLED") {
                        $status = "Cancelled";
                    } else {
                        $status = "Unsuccessful";
                    }
                } else {
                    $trans = Transaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first(array('referenceNumber', 'realPayTransaction_id', 'vcsTransaction_id', 'status'));

                    if ($trans != null && strtoupper($trans->status) == "SUCCESS") {
                        $status = "Successful";
                    } elseif ($trans != null && (strtoupper($trans->status) == "PENDING" || strtoupper($trans->status) == "A")) {
                        $status = "Pending";
                    } elseif ($trans != null && strtoupper($trans->status) == "PROCESSING") {
                        $status = "Processing";
                    } elseif ($trans != null && strtoupper($trans->status) == "CANCELLED") {
                        $status = "Cancelled";
                    } elseif ($trans != null && strtoupper($trans->status) == "0") {
                        $status = "Payment not initiated";
                    } elseif ($trans != null && $trans->vcsTransaction_id == null && $trans->realPayTransaction_id == null && $trans['referenceNumber'] != null && strtoupper($trans->status) == "0") {
                        $status = "Payment not initiated";
                    } else {
                        $status = "Payment Unsuccessful";
                    }
                }

                return $status;

            })->addColumn('kycStatus', function ($policy)
            {
                $kycStatus = KYC::where('customer_id', $policy->customer_id)
                    ->first('compliance');
                if($kycStatus){
                    if ($kycStatus->compliance == 1)
                    {
                        $kstatus = 'Completed';
                        return $kstatus;
                    }
                    else
                    {
                        $kstatus = 'Incomplete';
                    }

                    return $kstatus;
                }else{
                    return 'N/A';
                }

            })->addColumn('referenceNo', function ($policy)
            {
                if ($policy->customer_id != null) {
                    $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first();
                }

                if ($trans != null && $trans['referenceNumber'] != null) {
                    $referenceNumber = $trans['referenceNumber'];
                } else {
                    $referenceNumber = "Payment reference not generated";
                }

                return $referenceNumber;
            })
            ->addColumn('storeID', function ($policy)
            {
                if ($policy->storeID != NULL) {
                    $store = Stores::where('id',$policy->storeID)->first(array('name'));
                    if($store && $store->name != null){
                        return $store->name;
                    }else{
                        return 'Store not found';
                    }
                }
                else
                {
                    return "-";
                }
            })

            ->rawColumns(['storeID','payment_method','premium','vehicle_plate','branch', 'status', 'paymentStatus', 'kycStatus', 'referenceNo'])
            ->make(true);

    }

    public function realpayTransactionData(Request $request)
    {

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
        $searchValue = $search_arr['value']; // Search value
       // $data = RealpayPaymentRequest::orderBy('id','desc')->get();
        // Total records
        $totalRecords = RealpayPaymentRequest::select('count(*) as allcount')->count();

        $query = RealpayPaymentRequest::join('policies','policies.id','realpay_payment_request.policy_id')
            ->join('customer','customer.id','policies.customer_id')
            ->join('products','products.id','policies.product_id')
            ->join('product_plans','product_plans.id','policies.plan_id')
            ->orderBy('realpay_payment_request.policy_id', 'DESC');

        if($searchValue != null){
            $query->where('policies.policyNumber', 'like', '%' .$searchValue . '%')
                ->orWhere('policies.product_id', 'like', '%' .$searchValue . '%')
                ->orWhere('policies.premium', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' .$searchValue . '%')
                ->orWhere('product_plans.name', 'like', '%' .$searchValue . '%')
                ->orWhere('products.name', 'like', '%' .$searchValue . '%');
        }

        if ($request->policyStatus_filter != - 1)
        {
            $query->where('policies.status', $request->policyStatus_filter);
        }

        if($searchValue != null){
            $totalRecordswithFilter = RealpayPaymentRequest::join('policies','policies.policyNumber','realpay_payment_request.clientNumber')
                ->join('customer','customer.id','policies.customer_id')
                ->join('products','products.id','policies.product_id')
                ->join('product_plans','product_plans.id','policies.plan_id')
                ->where('policies.policyNumber', 'like', '%' .$searchValue . '%')
                ->orWhere('policies.premium', 'like', '%' .$searchValue . '%')
                ->orWhere('policies.product_id', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' .$searchValue . '%')
                ->orWhere('product_plans.name', 'like', '%' .$searchValue . '%')
                ->orWhere('products.name', 'like', '%' .$searchValue . '%')
                ->select('count(*) as allcount')
                ->count();
        }else{
            $totalRecordswithFilter = $query->count();
        }

        if($request->length == -1)
            $rowperpage = $totalRecordswithFilter;

        $records = $query->skip($start)
            ->take($rowperpage)
            ->get(
                [
                    'realpay_payment_request.id',
                    'policies.id as policy_id',
                    'policies.policyNumber',
                    'policies.agent_id',
                    'policies.premium',
                    'policies.first_premium_wvat as first_premium',
                    'policies.premium_freq',
                    'policies.product_id',
                    'policies.plan_id',
                    'policies.agent_id',
                    'policies.storeID',
                    'policies.billing_day',
                    'policies.billingStartDate',
                    'policies.status as policyStatus',
                    'realpay_payment_request.created_at as created_at',
                    'realpay_payment_request.status as paymentStatus',
                ]
            );

        $records = json_decode($records,true);

        $data_arr = array();
        $sno = $start+1;
        foreach($records as $record){
            if($record['id'])
                $id = $record['id'];
            else
                $id = 'N/A';

            if($record['policy_id'])
                $pid = $record['policy_id'];
            else
                $pid = 'N/A';

            if($record['policyStatus'] == 0) {
                $pstatus = 'Deactivated';
            }
            elseif($record['policyStatus'] == 1) {
                $pstatus = 'Activated'; //policyStatus
            }elseif ($record['policyStatus'] == 2){
                $pstatus = 'Cancelled'; //policyStatus
            }else{
                $pstatus = 'N/A';
            }

            if($record['premium_freq'] == 1) {
                $freq = 'Monthly';
            }
            elseif($record['premium_freq'] == 2) {
                $freq = 'Three Instalsments';
            }elseif ($record['premium_freq'] == 3){
                $freq = 'Annual';
            }else{
                $freq = 'N/A';
            }

            if($record['first_premium'] && $record['premium_freq'] == 1)
                $first_prem = 'P '.$record['first_premium'];
            else
                $first_prem = '-';

            if($record['premium'])
                $prem = 'P '.$record['premium'];
            else
                $prem = '-';

            if($record['billing_day'])
                $day = $record['billing_day'];
            else
                $day = '-'; //billingStartDate

            if($record['billingStartDate'])
                $date = $record['billingStartDate'];
            else
                $date = '-';


            if($record['product_id']){
                $product = Product::where('id',$record['product_id'])->first();
                if($product && $product->name)
                    $productname = $product->name;
                else
                    $productname = 'N/A';
            }else{
                $productname = 'N/A';
            }



            if($record['plan_id'] != null){
                $plan = Productplan::where('id',$record['plan_id'])->first();
                if($plan && $plan->name)
                    $planname = $plan->name;
                else
                    $planname = 'N/A';
            }else{
                $planname = 'N/A';
            }

            if ($record['agent_id'] != null)
            {
                $user = User::where('id', $record['agent_id'])
                    ->first(array(
                        'firstName',
                        'lastName'
                    ));
                if ($user != null)
                    $agentName = ucwords($user->firstName . ' ' . $user->lastName);
                else
                    $agentName = 'N/A';

            }
            else
            {
                $agentName = 'N/A';
            }

            if($record['storeID'] != null){
                $store = Stores::where('id',$record['storeID'])->first(array('name'));
                $storeName = $store != null ? $store->name : '-';
            }else{
                $storeName = '-';
            }

            if($record['policyNumber'])
                $policyNumber = $record['policyNumber'];
            else
                $policyNumber = 'N/A';


            $count = RealpayContractInstallments::where('contractNumber',$record['policy_id'])->count();
            $active = RealpayContractInstallments::where('contractNumber',$record['policy_id'])->where('InstalmentStatus','A')->get(array('id'));
            $success = RealpayContractInstallments::where('contractNumber',$record['policy_id'])->where('InstalmentStatus','S')->get(array('id'));
            $failed = RealpayContractInstallments::where('contractNumber',$record['policy_id'])->where('InstalmentStatus','F')->get(array('id'));

            if($count == 0){
                $payment = 'Failed';
            }else{
                $payment = 'Active: '.count($active) .', <br>Success: '.count($success).',<br> Failed: '.count($failed);
            }

            $data_arr[] = array(
                "id" => $id,
                "policy_id" => $policyNumber,
                "clientNumber" => $policyNumber,
                "contract" => $pid,
                "product_id" => $productname,
                "plan_id" => $planname,
                "first_premium_wvat" => $first_prem,
                "premium" => $prem,
                "frequency" => $freq,
                "billing_day" => $day,
                "agent" => $agentName,
                "store" => $storeName,
                "billing_date" =>$date,
                "policy_status" =>$pstatus,
                "payment_status" =>$payment,
            );
        }

        $data = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        echo json_encode($data);

        exit;
    }

    public function allPolicyVehicleData(Request $request)
    {
        $query = Policy::query();

        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }elseif ($request->filterDateFrom != '-1' && $request->filterDateto == '-1'){
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        $policy = $query->where('product_id',3)->orderBy('id','DESC')->get();


        return DataTables::of($policy)->editColumn('created_at', function ($policy)
        {
            return $policy->created_at;
        })->editColumn('policyNumber', function ($policy)
        {
            if ($policy->policyNumber != null)
            {
                if($policy && $policy->policyNumber)
                    $return = $policy->policyNumber;
                else
                    $return = 'N/A';
            }else{
                $return = 'N/A';
            }

            return $return;
        })->editColumn('store', function ($policy)
        {
            if ($policy->storeID != null)
            {
                $store = Stores::where('id',$policy->storeID)->first(array('name'));
                if($store && $store->name)
                    $return = $store->name;
                else
                    $return = 'Name not found';
            }else{
                $return = '-';
            }

            return $return;
        })->editColumn('leadsource', function ($policy)
        {
            if ($policy->leadSource != null)
            {
                $return = $policy->leadSource;
            }else{
                $return = '-';
            }

            return $return;
        })->editColumn('name', function ($policy)
        {
            if ($policy->customer_id != null)
            {
                $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
                if($customer != null) {
                    $return = $customer->firstName.' '.$customer->lastName;
                }
                else {
                    $return = 'N/A';
                }
            }else{
                $return = 'N/A';
            }

            return $return;
        })->addColumn('cellphone', function ($policy)
        {
            if ($policy->customer_id != null)
            {
                $customer = Customer::where('id',$policy->customer_id)->first(array('cellphone'));
                if($customer && $customer->cellphone) {
                    $return = $customer->cellphone;
                }
                else {
                    $return = 'N/A';
                }
            }else{
                $return = 'N/A';
            }
            return $return;
        })->addColumn('dob', function ($policy)
        {
            if ($policy->customer_id != null)
            {
                $customerNumber = CustomerProfile::where('customer_id', $policy->customer_id)
                    ->first(array('dob'));
                if($customerNumber)
                    return $customerNumber->dob;
                else
                    return 'N/A';
            }
            else
            {
                return '-';
            }

        })
        ->addColumn('vehiclePlate', function ($policy)
        {
            if ($policy->id != null)
            {
                $vehicle = Vehicle::where('policy_id',$policy->id)->first(array('vehiclePlate'));
                if($vehicle && $vehicle->vehiclePlate) {
                    return strtoupper($vehicle->vehiclePlate);
                }
                else{
                    return 'N/A';
                }
            }
            else
            {
                return 'N/A';
            }

        })
            ->addColumn('make', function ($policy)
            {
                if ($policy->id != null)
                {
                    $vehicle = Vehicle::where('policy_id',$policy->id)->first(array('make'));
                    if($vehicle && $vehicle->make) {
                        return $vehicle->make;
                    }
                    else{
                        return 'N/A';
                    }
                }
                else
                {
                    return 'N/A';
                }

            })
            ->addColumn('model', function ($policy)
            {
                if ($policy->id != null)
                {
                    $vehicle = Vehicle::where('policy_id',$policy->id)->first(array('model'));
                    if($vehicle && $vehicle->model) {
                        return $vehicle->model;
                    }
                    else{
                        return 'N/A';
                    }
                }
                else
                {
                    return 'N/A';
                }

            })
            ->addColumn('year', function ($policy)
            {
                if ($policy->id != null)
                {
                    $vehicle = Vehicle::where('policy_id',$policy->id)->first(array('year'));
                    if($vehicle && $vehicle->year) {
                        return $vehicle->year;
                    }
                    else{
                        return 'N/A';
                    }
                }
                else
                {
                    return 'N/A';
                }

            })
            ->addColumn('is_imported', function ($policy)
            {
                if ($policy->id != null)
                {
                    $vehicle = Vehicle::where('policy_id',$policy->id)->first(array('is_imported'));
                    if($vehicle && $vehicle->is_imported == 1) {
                        return 'Yes';
                    }
                    else{
                        return 'No';
                    }
                }
                else
                {
                    return 'No';
                }

            })->addColumn('premium_freq', function ($policy)
            {
                if ($policy->premium_freq != null)
                {
                    if ($policy->premium_freq != null){
                        if($policy->premium_freq == 1)
                            return 'Monthly';
                        elseif($policy->premium_freq == 2)
                            $freq =  'Three Instalments';
                        elseif($policy->premium_freq == 3)
                            $freq = 'Annual';
                        else
                            $freq = 'Frequency not described';

                    }else{
                        $freq = 'Frequency not found';
                    }
                }
                else
                {
                    $freq = 'N/A';
                }

                return $freq;

            })->addColumn('rate', function ($policy)
            {
                if ($policy->quoteNumber != null)
                {
                    $mt = MotorComprehensiveQuotes::where('quoteNumber',$policy->quoteNumber)->first(array('premium_rate','premiumAnnually','estimatedValue'));

                    if($mt != null){
                        if($mt->premium_rate){
                            return number_format((float) $mt->premium_rate, 2, '.', '').'%';
                        }else {
                           if($mt->premiumAnnually && $mt->estimatedValue){
                                return number_format((float) ($mt->premiumAnnually/$mt->estimatedValue)*100, 2, '.', '').'%';
                           }else{
                                return 'N/A';
                           }
                        }
                    }else{
                        return 'N/A';
                    }
                }
                else
                {
                    return '-';
                }

            })
            ->addColumn('estimated_value', function ($policy)
            {
                if ($policy->sum_assured != null)
                {
                    if($policy->sum_assured != null){
                        return $policy->sum_assured;
                    }else{
                        return 'N/A';
                    }
                }
                else
                {
                    return '-';
                }

            })
            ->addColumn('premium', function ($policy)
            {
                if($policy->premium){
                   return $policy->premium;
                }else{
                    return 'N/A';
                }
            })->editColumn('agent', function ($policy)
            {
                if($policy->agent_id){
                    $u = User::where('id',$policy->agent_id)->first(array('firstName','lastName'));
                    if($u){
                        if($u->firstName || $u->lastName){
                            return $u->firstName.' '.$u->lastName;
                        }else{
                            return 'N/A';
                        }
                    }else{
                        return 'N/A';
                    }
                }else{
                    return '-';
                }


            })->addColumn('payment_status', function ($policy)
            {
                $trans = PaymentTransaction::where('policyNumber', $policy->policyNumber)->orderBy('id', 'DESC')->first();

                if($trans != null) {
                    if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                        $status = 'Payment Successful';
                    } elseif ($trans != null && (strtoupper($trans->status) == "PENDING" || strtoupper($trans->status) == "A")) {
                        $status = 'Payment Pending';
                    } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                        $status = 'Payment Processing';
                    } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED"  || strtoupper($trans['status']) == "I") {
                        $status = 'Payment Cancelled';
                    } else {
                        $status = 'Payment Unsuccessful';
                    }
                }else{
                    $trans = Transaction::where('policyNumber', $trans['policyNumber'])->orderBy('id', 'DESC')->first(array('referenceNumber','realPayTransaction_id','vcsTransaction_id','status'));

                    if ($trans != null && strtoupper($trans['status']) == "SUCCESS") {
                        $status = 'Payment Successful';
                    } elseif ($trans != null && (strtoupper($trans['status']) == "PENDING" || strtoupper($trans['status']) == "A")) {
                        $status = 'Payment Pending';
                    } elseif ($trans != null && strtoupper($trans['status']) == "PROCESSING") {
                        $status = 'Payment Processing';
                    } elseif ($trans != null && strtoupper($trans['status']) == "CANCELLED" || strtoupper($trans['status']) == "I") {
                        $status = 'Payment Cancelled';
                    } elseif ($trans != null && strtoupper($trans['status']) == "0") {
                        $status = 'Payment not initiated';
                    } elseif ($trans != null && $trans['vcsTransaction_id'] == null && $trans['realPayTransaction_id'] == null && $trans['referenceNumber'] != null && strtoupper($trans['status']) == "0") {
                        $status = 'Payment not initiated';
                    } else {
                        $status = 'Payment Unsuccessful';
                    }

                }



//                if($policy->policyNumber){
//                    $payment = Transaction::where('policyNumber', $policy->policyNumber)
//                        ->orderBy('id', 'DESC')
//                        ->first(array('status'));
//
//                    if($payment && $payment->status != null){
//                        if($payment->status == 'A'){
//                            $paymentStatus = 'Active';
//                        }elseif ($payment->status == 'R'){
//                            $paymentStatus = 'Retry';
//                        }elseif ($payment->status == 'I'){
//                            $paymentStatus = 'Cancelled';
//                        }elseif ($payment->status == 0){
//                            $paymentStatus = 'Payment not completed';
//                        }else{
//                            $paymentStatus = ucfirst($payment->status);
//                        }
//                    }else{
//                        $paymentStatus = 'Payment not initiated';
//                    }
//               }else{
//                    $paymentStatus = 'N/A';
//                }

                return $status;

            })->addColumn('kyc_status', function ($policy)
            {
                $kycStatus = KYC::where('customer_id', $policy->customer_id)
                    ->first('compliance');
                if($kycStatus){
                    if ($kycStatus->compliance == 1)
                    {
                        $kstatus = 'Completed';
                        return $kstatus;
                    }
                    else
                    {
                        $kstatus = 'Incomplete';
                    }

                    return $kstatus;
                }else{
                    return 'N/A';
                }

            })->addColumn('policy_status', function ($policy)
            {
                if ($policy->status != 0){
                        if($policy->status == 0)
                            return 'Deactivated';
                        elseif($policy->status == 1)
                            return 'Activated';
                        elseif($policy->status == 2)
                            return 'Cancelled';
                        else
                            return 'N/A';

                }else{
                    return 'Deactivated';
                }
            })
            ->rawColumns(['store','premium_freq','leadsource','policy_status','estimated_value','kyc_status','payment_status', 'agent', 'premium', 'is_imported', 'rate','policyNumber','cellphone','name'])
            ->make(true);
    }

    public function getVehicleReport(){
        try{
            if (Auth::user()->hasPermissionTo('policy-report-list'))
            {
                $products = Product::with('type')->where('status', 1)
                    ->get(array(
                        'id',
                        'product_type_id',
                        'name',
                        'is_motor_items',
                        'kyc_customer',
                        'has_vehicle',
                        'has_subApplicant'
                    ));
                $productPlans = Productplan::where('status', 1)->get(array(
                    'id',
                    'name'
                ));
                $customerNumbers = Customer::where('id', '!=', null)->get();
                $policyAgents = Policy::with('user')->where('agent_id', '!=', 'null')
                    ->groupBy('agent_id')
                    ->get();

                //  return response($policyLeadAgent);
                return view('admin.reports.vehicle_report', compact('products', 'productPlans', 'customerNumbers', 'policyAgents'));
            }
            else
            {
                return Redirect::back()
                    ->with('error', 'Sorry! You do not have permission to access this page!');
            }
        }catch(\Exception $e){

        }
    }

    public function realpayTransactionExport(Request $request){
        try{
            return Excel::download(new RealpayTransactions($request->all()), 'RealpayTransactions.csv');
        }catch(Exception $e){

        }
    }

    public function transactionReportExport(Request $request){
        try{
            return Excel::download(new TransactionReportProductExcel($request->all()), 'TransactionReportProduct.xlsx');
        }catch(Exception $e){

        }
    }
    public function transactionSummaryProductReportExport(Request $request){
        try{
            return Excel::download(new TransactionSummaryProductReportExport($request->all()), 'TransactionSummaryProductReportExport.xlsx');
        }catch(Exception $e){

        }
    }

    public function bookOfBusinessReportExport(Request $request){
        try{
            return Excel::download(new BookOfBusinessReportExport($request->all()), 'BookOfBusinessReportExport.xlsx');
        }catch(Exception $e){

        }
    }
    public function detailedAgeAnalysis(){
        return view('admin.reports.detailedAnalysisReport');
    }

    public function detailedAgeAnalysisData()
    {
        $paymentTxn = PaymentTransaction::query();
        return DataTables::eloquent($paymentTxn)
            ->editColumn('policyNumber', function ($policy) {
                $policyNumber = 'NA';
                if($policy->policyNumber !=null){
                  $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->editColumn('customer_name', function ($policy) {
                $name = 'NA';
                if($policy->policyNumber !=null){
                    $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                    if($policy){
                        $customer = Customer::where('id',$policy->customer_id)->first();
                        if($customer){
                            $name = $customer->firstName.' '.$customer->lastName;
                        }

                    }
                }
                return $name;
            })
            ->editColumn('classification', function ($policy) {
                return 'Payments';
            })
            ->editColumn('referenceNumber', function ($policy) {
               $referenceNumber = 'NA';
                if(!empty($policy->referenceNumber)){
                    $referenceNumber = $policy->referenceNumber;
                }

                return $referenceNumber;

            })
            ->editColumn('product', function ($policy) {
                $product = 'NA';
                if($policy->policyNumber !=null){
                    $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                    if($policy){
                        $product = Product::where('id',$policy->product_id)->first();
                        if($product){
                            $product = $product->name;
                        }

                    }
                }

                return $product;

            })
            ->editColumn('plan', function ($policy) {
                $plan = 'NA';
                $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                if($policy){
                    $plan = Productplan::where('id',$policy->plan_id)->first();
                    if($plan){
                        $plan = $plan->name;
                    }

                }

                return $plan;

            })
            ->editColumn('vehicle_plate', function ($policy) {

                $vehicle_plate = 'NA';
                $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                if($policy){
                    $vehicle_plate = Vehicle::where('policy_id',$policy->id)->first();
                    if($vehicle_plate){
                        $vehicle_plate = $vehicle_plate->vehiclePlate;
                    }

                }

                return $vehicle_plate;

            })
            ->editColumn('premium', function ($policy) {
                $premium = 'NA';
                $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                if($policy){
                    $premium = $policy->premium;

                }
               return $premium;

            })
            ->editColumn('invoice_date', function ($policy) {
                $invoice_date = 'NA';
                $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                if($policy){
                    $invoice_date = $policy->policyActivatedDate;

                }
                return $invoice_date;
            })
            ->editColumn('number_of_days_remaining', function ($policy) {
                $diff = 'NA';
                $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                if($policy){
                    $date = Carbon::parse($policy->policyActivatedDate);
                    $now = Carbon::now();

                    $diff = $date->diffInDays($now);

                }

                return $diff;
            })
            ->rawColumns(['status'])
            ->make(true);
    }

    public function transactionReportProduct()
    {
        return view('admin.reports.transaction_report_product');
    }

    public function transactionSummaryProductReport()
    {
        return view('admin.reports.transaction_summary_product_report');
    }
    public function bookOfBusinessReport()
    {
        return view('admin.reports.book_of_business_report');
    }

    public function inforceReport()
    {
        return view('admin.reports.inforce_report');
    }
    public function policyStatusReport(){
        return view('admin.reports.policy_Status_Report');
    }
    public function earnedPremiumReport()
    {
        return view('admin.reports.earned_primium_unearned');
    }
    public function earnedPremiumReportExport(Request $request){
        try{
            return Excel::download(new EarnedpremiumwithExport($request->all()), 'EarnedPremiumWithExport.xlsx');
        }catch(Exception $e){

        }
    }

    public function earnedPremiumReportData(Request $request)
    {
        // $policy = Policy::where('id',1881)->orderBy('created_at', 'DESC');
        $policy = Policy::orderBy('created_at', 'DESC');
        $now = Carbon::now();
        $financialYearStart = Carbon::now();
        $financialYearStart->set('month', 7);
        $financialYearStart->set('day', 1);
        if($financialYearStart->greaterThan($now))
            $financialYearStart->subYear();

        $financialYearEnd = Carbon::now();
        $financialYearEnd->set('month', 7);
        $financialYearEnd->set('day', 1);
        if($financialYearEnd->lessThan($now))
            $financialYearEnd->addYear();
        $financialYearDays =  $financialYearStart->diffInDays($financialYearEnd);

        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($policy)
            ->editColumn('policyNumber', function ($policy) {
                $policyNumber = 'NA';
                if($policy->policyNumber !=null){
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('name', function ($policy) {
                $name= "NA";
                $product_name = Product::where('id',$policy->product_id)->first(array('name'));
                if($product_name->name !=null){
                    $name =  $product_name->name;
                }
                return $name;
            })
            ->addColumn('insured_name', function ($policy) {
                $customer_name = "NA";
                if($policy->id !=null){
                    $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
                    if($customer){
                        $customer_name =  $customer->firstName.' '.$customer->lastName;
                    }else{
                        $customer_name = '-';
                    }
                }
                return $customer_name;
            })
            ->addColumn('s_transaction_type', function ($policy) {
                $s_transaction_type = "NA";
                if($policy->policyNumber !=null){
                    $pos = strpos($policy->created_at, '/');
                    if ($pos !== false) {
                        $s_transaction_type = Carbon::createFromFormat('d/m/Y', $policy->created_at)->format('d-m-Y');
                    } else {
                        $s_transaction_type = Carbon::parse($policy->created_at)->diffInYears(Carbon::now());
                        if($s_transaction_type == 2){
                            $s_transaction_type="Cancel";
                        }elseif ($s_transaction_type == 0){
                            $s_transaction_type="New Business";
                        }else{
                            $s_transaction_type="Renew";
                        }
                    }
                }
                return $s_transaction_type;
            })
            ->addColumn('no_of_days', function ($policy) use ($financialYearStart) {
                if(Carbon::parse($policy->created_at)->greaterThan($financialYearStart)) {
                    $no_of_days =  Carbon::parse($policy->created_at)->diffInDays(Carbon::now());
                } else {
                    $no_of_days =  $financialYearStart->diffInDays(Carbon::now());
                }
                return $no_of_days;
            })
            ->addColumn('written_premium', function ($policy) {
                if($policy->premium_freq == 1) //For Monthly Motor Comp remove 8% Sales Tax
                    $written_premum = round(((($policy->premium * 12) * 0.88) * 0.92), 2);
                else
                    $written_premum = round(($policy->premium * 12) * 0.88, 2);
                return 'P '.$written_premum;
            })
            ->addColumn('earned_premium', function ($policy) use ($financialYearStart) {
                if($policy->premium_freq == 1) //For Monthly Motor Comp remove 8% Sales Tax
                    $written_premum = round(((($policy->premium * 12) * 0.88) * 0.92), 2);
                else
                    $written_premum = round(($policy->premium * 12) * 0.88, 2);
                if(Carbon::parse($policy->created_at)->greaterThan($financialYearStart)) {
                    $no_of_days =  Carbon::parse($policy->created_at)->diffInDays(Carbon::now());
                } else {
                    $no_of_days =  $financialYearStart->diffInDays(Carbon::now());
                }
                return round(($written_premum/365.25) * $no_of_days, 2);
            })
            ->addColumn('unearned_premium', function ($policy) use ($financialYearStart, $financialYearDays) {
                if($policy->premium_freq == 1) //For Monthly Motor Comp remove 8% Sales Tax
                    $written_premum = round(((($policy->premium * 12) * 0.88) * 0.92), 2);
                else
                    $written_premum = round(($policy->premium * 12) * 0.88, 2);
                if(Carbon::parse($policy->created_at)->greaterThan($financialYearStart)) {
                    $no_of_days =  Carbon::parse($policy->created_at)->diffInDays(Carbon::now());
                } else {
                    $no_of_days =  $financialYearStart->diffInDays(Carbon::now());
                }
                $earned_premium = ($written_premum/365.25) * $no_of_days;
                $earned_premium_1year = ($written_premum/365.25) * $financialYearDays;
                return round(($earned_premium_1year - $earned_premium), 2);
            })
            ->addColumn('total_earned_premium', function ($policy) {
                if($policy->premium_freq == 1) //For Monthly Motor Comp remove 8% Sales Tax
                    $written_premum = round(((($policy->premium * 12) * 0.88) * 0.92), 2);
                else
                    $written_premum = round(($policy->premium * 12) * 0.88, 2);
                $no_of_days =  Carbon::parse($policy->created_at)->diffInDays(Carbon::now());
                return 'P '.round(($written_premum/365.25) * $no_of_days, 2);
            })
            ->addColumn('term_start_date', function ($policy) {
                $term_start= 'NA';
                $pos = strpos($policy->billingStartDate, '/');
                if ($pos !== false) {
                    $term_start = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
                }
                else {
                    $term_start = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                }
                return $term_start;
            })
            ->addColumn('term_end_date', function ($policy) {
                $term_end= 'NA';
                $pos = strpos($policy->billingStartDate, '/');
            if ($pos !== false) {
                $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
            }
            if($policy->premium_freq > 1 )
            {
                $term_end = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
            } else {
                $term_end = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            }
            return  $term_end ;
            })
            ->addColumn('d_trance_effective_from', function ($policy) {
                $term_start= 'NA';
                $pos = strpos($policy->billingStartDate, '/');
                if ($pos !== false) {
                    $term_start = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
                }
                else {
                    $term_start = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                }
                return $term_start;
            })
            ->addColumn('d_trance_effective_to', function ($policy) {
                $term_end= 'NA';
                $pos = strpos($policy->billingStartDate, '/');
            if ($pos !== false) {
                $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
            }
            if($policy->premium_freq > 1 )
            {
                $term_end = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
            } else {
                $term_end = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            }
            return  $term_end ;
            })
            ->addColumn('booking_date', function ($policy) {
                $booking_date = "NA";
                if($policy->policyNumber !=null){
                    $booking_date= Carbon::parse($policy->created_at)->format('d-m-Y');
                }
                return $booking_date;
            })
            ->rawColumns(['name','booking_date','d_trance_effective_to'])
            ->make(true);

    }

    public function writtenPremiumReport()
    {
        return view('admin.reports.written_premium_report');
    }
    public function reInsuranceClaimsProfile()
    {
        return view('admin.reports.re_insurance_claims_profile');
    }
    public function claimAsOnDate()
    {
        return view('admin.reports.claim_as_on_date');
    }
    public function subLedgerReport()
    {
        return view('admin.reports.sub-ledger_report');
    }
    public function transactionReportProductData(Request $request)
    {

        $policy = Policy::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

//        $policy = Policy::query();
        return DataTables::eloquent($policy)
            ->editColumn('policyNumber', function ($policy) {
                $policyNumber = 'NA';
                if($policy->policyNumber !=null){
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->editColumn('customer_name', function ($policy) {
                $name = 'NA';
                if($policy->policyNumber !=null){
                    $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                    if($policy){
                        $customer = Customer::where('id',$policy->customer_id)->first();
                        if($customer){
                            $name = $customer->firstName;
                        }

                    }
                }
                return $name;
            })
            ->editColumn('transaction', function ($policy) {

                if($policy->policyNumber !=null){
                    $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                    if($policy){
                        $ledger = Ledger::where('customer_id',$policy->customer_id)->first();//policyledger model
                        if($ledger){
                            $transaction = $ledger->orig_trans;
                        }
                        else{
                            $transaction = 'NA';
                        }

                    }
                }
                return $transaction;
            })
            ->editColumn('address', function ($policy) {
                $address = 'NA';
                if($policy->policyNumber !=null){
                    $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                    if($policy){
                        $customerProfile = CustomerProfile::where('customer_id',$policy->customer_id)->first();
                        if($customerProfile){
                            $address = $customerProfile->address;
                        }

                    }
                }
                return $address;
            })
            ->editColumn('country', function ($policy) {
                $country = 'NA';
                if($policy->policyNumber !=null){
                    $CustomerProfile = CustomerProfile::where('customer_id',$policy->customer_id)->first();
                    if($policy){
                        $country = Country::where('id',$CustomerProfile->countryId)->first();
                        if($country){
                            $country = $country->name;
                        }
                        else{
                            $country = 'Botswana';
                        }

                    }
                }
                return $country;
            })
            ->editColumn('city', function ($policy) {
                $city = 'NA';
                if($policy->policyNumber !=null){
                    $policy = Policy::where('policyNumber',$policy->policyNumber)->first();
                    if($policy){
                        $customerProfile = CustomerProfile::where('customer_id',$policy->customer_id)->first();
                        if($customerProfile){
                            $city = $customerProfile->city;
                        }

                    }
                }
                return $city;
            })
            ->editColumn('state', function ($policy) {
                $state = 'NA';
                if($policy->policyNumber !=null){
                    $CustomerProfile = CustomerProfile::where('customer_id',$policy->customer_id)->first();
                    if($CustomerProfile){
                        $state = State::where('id',$CustomerProfile->state)->first();
                        if($state){
                            $state = $state->name;
                        }
                        else{
                            $state = 'NA';
                        }
                    }
                }
                return $state;
            })
            ->editColumn('tran_sub', function ($policy) {
                if($policy->policyNumber !=null){
                    $tran_sub = '-';
                }
                return $tran_sub;
            })
            ->editColumn('zip', function ($policy) {
                if($policy->policyNumber !=null){
                    $zip = '-';
                }
                return $zip;
            })
            ->editColumn('cov_A', function ($policy) {
                if($policy->policyNumber !=null){
                    $cov_A = '-';
                }
                return $cov_A;
            })
            ->editColumn('term_end', function ($policy) {
                if($policy->policyNumber !=null){
                    $term_end = '-';
                }
                return $term_end;
            })
            ->editColumn('Act_date', function ($policy) {
                if($policy->policyNumber !=null){
                    $Act_date = '-';
                }
                return $Act_date;
            })
            ->editColumn('inforce', function ($policy) {
                if($policy->policyNumber !=null){
                    $inforce = '-';
                }
                return $inforce;
            })
            ->editColumn('pre_change', function ($policy) {
                if($policy->policyNumber !=null){
                    $pre_change = '-';
                }
                return $pre_change;
            })
            ->editColumn('agn_code', function ($policy) {
                if($policy->policyNumber !=null){
                    $agn_code = '-';
                }
                return $agn_code;
            })
            ->editColumn('agency_name', function ($policy) {
                if($policy->policyNumber !=null){
                    $agency_name = '-';
                }
                return $agency_name;
            })
            ->editColumn('agency_email', function ($policy) {
                if($policy->policyNumber !=null){
                    $agency_email = '-';
                }
                return $agency_email;
            })
            ->editColumn('agency_sub_agent_name', function ($policy) {
                if($policy->policyNumber !=null){
                    $agency_sub_agent_name = '-';
                }
                return $agency_sub_agent_name;
            })
            ->editColumn('updated_by', function ($policy) {
                if($policy->policyNumber !=null){
                    $updated_by = '-';
                }
                return $updated_by;
            })
            ->editColumn('updated_date', function ($policy) {
                if($policy->policyNumber !=null){
                    $updated_date = '-';
                }
                return $updated_date;
            })
            ->editColumn('note', function ($policy) {
                if($policy->policyNumber !=null){
                    $note = '-';
                }
                return $note;
            })
            ->editColumn('anniversary_start', function ($policy) {
                if($policy->policyNumber !=null){
                    $anniversary_start = '-';
                }
                return $anniversary_start;
            })
            ->editColumn('anniversary_end', function ($policy) {
                if($policy->policyNumber !=null){
                    $anniversary_end = '-';
                }
                return $anniversary_end;
            })
            ->editColumn('anniversary_seque', function ($policy) {
                if($policy->policyNumber !=null){
                    $anniversary_seque = '-';
                }
                return $anniversary_seque;
            })
            ->editColumn('term_reset_counter', function ($policy) {
                if($policy->policyNumber !=null){
                    $term_reset_counter = '-';
                }
                return $term_reset_counter;
            })
            ->rawColumns(['status'])
            ->make(true);
    }


    public function transactionSummaryProductReportData(Request $request)
    {
        $product = Product::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $product->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $product->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($product)
            ->editColumn('name', function ($product) {
                $name = 'NA';
                if($product->id !=null){
                    $name = $product->name;
                }
                return $name;
            })
            ->addColumn('product_id_count', function ($product) {
                $product_id_count = 'NA';
                    if($product->id !=null) {
                        $product_id_count = Policy::where('product_id',$product->id)->count();
                    }
                return $product_id_count;
            })
            ->editColumn('status', function ($product) {
                $status = 'Deactive';
                if($product->id !=null){
                    if($product->status == 1){
                        $status = $product->status;
                    }
                }
                return $status;
            })
            ->addColumn('endorse', function ($product) {
                $endorse = 'N\A';
                if($product->id !=null){
                        $endorse = 0;
                }
                return $endorse;
            })
            ->addColumn('total', function ($product) {
                $total = 0;
                if($product->id !=null){
                    $product_id_count = Policy::where('product_id',$product->id)->count();
                    $status = $product->status;
                    $endorse = 0;
                    $total = $product_id_count + $status + $endorse;
                }
                return $total;
            })

            ->rawColumns(['status','total','endorse','product_id_count','name'])
            ->make(true);
    }

    public function bookOfBusinessReportData(Request $request)
    {
        $bookOfBusiness = Policy::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $bookOfBusiness->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $bookOfBusiness->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($bookOfBusiness)
            ->addColumn('owner_name', function ($bookOfBusiness) {
                if($bookOfBusiness->id !=null){
                    $customer = Customer::where('id',$bookOfBusiness->customer_id)->first(array('firstName','lastName'));
                    if($customer){
                        $customer_name =  $customer->firstName.' '.$customer->lastName;
                    }else{
                        $customer_name = '-';
                    }
                }
                return $customer_name;
            })
            ->addColumn('agent_name', function ($bookOfBusiness) {
                if($bookOfBusiness->id !=null){
                    $user = User::where('id',$bookOfBusiness->agent_id)->first(array('firstName','lastName'));
                    if($user){
                        $agent_name =  $user->firstName.' '.$user->lastName;
                    }else{
                        $agent_name = '-';
                    }
                }
                return $agent_name;
            })
            ->addColumn('policyNumber', function ($bookOfBusiness) {
                $policy_no = 'NA';
                if($bookOfBusiness->id !=null){
                    $policy_no = $bookOfBusiness->policyNumber;
                }
                return $policy_no;
            })
            ->addColumn('inception', function ($bookOfBusiness) {
                $inception = 'NA';
                if($bookOfBusiness->created_at !=null){
                    $inception = Carbon::parse($bookOfBusiness->created_at)->format('d-m-Y');
                }
                return $inception;
            })

            ->addColumn('term', function ($bookOfBusiness) {
                $term = 'NA';
                if($bookOfBusiness->created_at !=null){
                    $term = Carbon::parse($bookOfBusiness->created_at)->diffInYears(Carbon::now());
                }
                return $term;
            })
            ->editColumn('trans_type', function ($bookOfBusiness) {
                $tran_type =  $bookOfBusiness->status;
                if($tran_type ==2){
                    $tran_type = 'CANCEL';
                }
                else{
                    $tran_type = 'RENEW';
                }
                return $tran_type;
            })
            ->editColumn('trans_sub_type', function ($bookOfBusiness) {
                $trans_sub_type = $bookOfBusiness->id ;
                if($trans_sub_type !=null){
                    $trans_sub_type = '-';
                }
                else{
                    $trans_sub_type = "-";
                }
                return $trans_sub_type;
            })

            ->editColumn('status', function ($bookOfBusiness) {
                $status = 'NA';

                if($bookOfBusiness->id !=null){
                    $status = $bookOfBusiness->status;
                    if ($status ==0){
                        $status ="Active";
                    }
                    elseif ($status ==1){
                        $status ="InActive";
                    }
                    else{
                        $status ="Cancel";
                    }
                }
                return $status;
            })
            ->addColumn('inforce_prem', function ($bookOfBusiness) {
                $inforce_prem = $bookOfBusiness->premium ;
                if($inforce_prem !=null){
                    $inforce_prem = $bookOfBusiness->premium;
                }
                else{
                    $inforce_prem = '-';
                }
                return $inforce_prem;
            })
            ->addColumn('term_start', function ($bookOfBusiness) {
                $term_start= 'NA';
                $pos = strpos($bookOfBusiness->billingStartDate, '/');
                if ($pos !== false) {
                    $term_start = Carbon::createFromFormat('d/m/Y', $bookOfBusiness->billingStartDate)->format('d-m-Y');
                }
                else {
                    $term_start = Carbon::parse($bookOfBusiness->billingStartDate)->format('d-m-Y');
                }
                return $term_start;
            })
            ->addColumn('term_end', function ($bookOfBusiness) {
                $term_end= 'NA';
                $pos = strpos($bookOfBusiness->billingStartDate, '/');
            if ($pos !== false) {
                $bookOfBusiness->billingStartDate = Carbon::createFromFormat('d/m/Y', $bookOfBusiness->billingStartDate)->format('d-m-Y');
            }
            if($bookOfBusiness->premium_freq > 1 )
            {
                $term_end = Carbon::parse($bookOfBusiness->billingStartDate)->addYear()->subDay()->format('d-m-Y');

            } else {
                $term_end = Carbon::parse($bookOfBusiness->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            }
            return  $term_end ;
            })
            ->rawColumns(['owner_name'])
            ->make(true);
    }

    public function inforceReportData(Request $request)
    {
//        return response()->json('test',401);
        $policyLedger = Ledger::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policyLedger->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policyLedger->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($policyLedger)
            ->addColumn('owner_name', function ($policyLedger) {
                $customer_name = 'NA';
                if($policyLedger->id !=null){
                    $customer = Customer::where('id',$policyLedger->customer_id)->first();
                    $customer_name = $customer->firstName;
                }
                return $customer_name;
            })
            ->editColumn('id', function ($policyLedger) {
                $id = 'NA';
                if($policyLedger->id !=null){
                    $id = $policyLedger->id;
                }
                return $id;
            })
            ->editColumn('name', function ($policyLedger) {
                $name = 'NA';
                if($policyLedger->id !=null){
                    $policy = Policy::where('id',$policyLedger->policy_id)->first();
                    $product = Product::where('id',$policy->product_id)->first();
                    $name = $product->name;
                }
                return $name;
            })
            ->editColumn('policy_no', function ($policyLedger) {
                $policyNo = 'NA';
                if($policyLedger->id !=null){
                    $policy = Policy::where('id',$policyLedger->policy_id)->first();
                    $policyNo = $policy->policyNumber;
                }
                return $policyNo;
            })
            ->addColumn('inforce_prm', function ($policyLedger) {
                $inforcePrm = 'NA';
                if($policyLedger->id !=null){
                    $inforcePrm = '-';
                }
                return $inforcePrm;
            })
            ->addColumn('seq', function ($policyLedger) {
                $seq = 'NA';
                if($policyLedger->id !=null){
                    $seq = '-';
                }
                return $seq;
            })
            ->addColumn('start_date', function ($policyLedger) {
                $startDate = 'NA';
                if($policyLedger->id !=null){
                    $startDate = $policyLedger->created_at;
                }
                return $startDate;
            })
            ->addColumn('end_date', function ($policyLedger) {
                $endDate = 'NA';
                if($policyLedger->id !=null){
                    $endDate = '-';
                }
                return $endDate;
            })
            ->addColumn('agency', function ($policyLedger) {
                $agency = 'NA';
                if($policyLedger->id !=null){
                    $agency = '-';
                }
                return $agency;
            })
            ->addColumn('agencyCode', function ($policyLedger) {
                $agencyCode = 'NA';
                if($policyLedger->id !=null){
                    $agencyCode = '-';
                }
                return $agencyCode;
            })
            ->addColumn('city_name', function ($policyLedger) {
                $cityName = 'NA';
                if($policyLedger->id !=null){
                    $cityName = '-';
                }
                return $cityName;
            })
            ->addColumn('renewal_plan_code', function ($policyLedger) {
                $renewalPlanCode = 'NA';
                if($policyLedger->id !=null){
                    $renewalPlanCode = '-';
                }
                return $renewalPlanCode;
            })

            ->rawColumns(['owner_name'])
            ->make(true);
    }

    public function writtenPremiumReportData(Request $request)
    {
        $now = Carbon::now();
        $financialYearStart = Carbon::now();
        $financialYearStart->set('month', 7);
        $financialYearStart->set('day', 1);
        if($financialYearStart->greaterThan($now))
            $financialYearStart->subYear();

        $policy = Policy::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

return DataTables::eloquent($policy)
    ->addColumn('policy_no', function ($policy) {

            $policy_no = $policy->policyNumber;
        // }
        return $policy_no;
    })
    ->addColumn('insured_name', function ($policy) {
        $insured_name = 'NA';
            $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
            if($customer)
            {
                $insured_name = $customer->firstName.' '.$customer->lastName;
            }

        return $insured_name;
    })
    ->addColumn('seq', function ($policy) {
        $seq = 'NA';
        if($policy->id !=null){
            $seq = '-';
        }
        return $seq;
    })
   ->editColumn('trans_type', function ($policy) use ($financialYearStart){

        if($policy->created_at->greaterThan($financialYearStart))
            return "NEW BUSINESS";
        else
            return "RE ISSUE";

    })
    ->addColumn('term_start', function ($policy) {

        $policyLedger = Ledger::where('policy_id',$policy->id)->first(array('accounting_date'));
        if($policyLedger)
        {
                $pos = strpos($policyLedger->accounting_date, '/');
            if ($pos !== false) {
                $term_start = Carbon::createFromFormat('d/m/Y', $policyLedger->accounting_date)->format('d-m-Y');
            } else {
                $term_start = Carbon::parse($policyLedger->accounting_date)->format('d-m-Y');
            }
        }
        else
        {
            $term_start = 'NA';
        }
        return $term_start;
    })
    ->addColumn('term_end', function ($policy) {

        $policyLedger = Ledger::where('policy_id',$policy->id)->first(array('accounting_date'));
        if($policyLedger)
        {
                $pos = strpos($policyLedger->accounting_date, '/');
            if ($pos !== false) {
                $policyLedger->accounting_date = Carbon::createFromFormat('d/m/Y', $policyLedger->accounting_date)->format('d-m-Y');
            }
            if($policy->premium_freq == 1 || $policy->premium_freq == NULL)
            {
                $term_end = Carbon::parse($policyLedger->accounting_date)->addMonth()->subDay()->format('d-m-Y');
            } else {
                $term_end = Carbon::parse($policyLedger->accounting_date)->addYear()->subDay()->format('d-m-Y');
            }
        }
        else
        {
            $term_end = 'NA';
        }

        return $term_end;
    })
    ->addColumn('booking_date', function ($policy) {
        $booking_date = 'NA';
            $policyLedger = Ledger::where('policy_id',$policy->id)->first(array('invoice_date'));
            if($policyLedger)
            {
                $booking_date = Carbon::parse($policyLedger->invoice_date)->format('d-m-Y');
            }
        return $booking_date;
    })
    ->addColumn('trans_eft_start', function ($policy) {
        $trans_eft_start = 'NA';
            $policyLedger = Ledger::where('policy_id',$policy->id)->first(array('eff_date'));
            if($policyLedger)
            {
                $trans_eft_start = Carbon::parse($policyLedger->eff_date)->format('d-m-Y');
            }
        return $trans_eft_start;
    })
    ->addColumn('trans_eft_end', function ($policy) {
        $trans_eft_end = 'NA';
        if($policy->id !=null){
            $trans_eft_end = '-';
        }
        return $trans_eft_end;
    })
    ->addColumn('written_premum', function ($policy) {
        $p = 'P ';
                $written_premum = $policy->premium;
        return $p.$written_premum;
    })

    ->rawColumns(['written_premum','policy_no'])
    ->make(true);
    }

    public function reInsuranceClaimsProfileData(Request $request)
    {
        $claim = ClaimReservesCoverage::join('claims', 'claims.id', '=', 'claim_reserves_coverages.claim_id')->join('claim_reserves', 'claim_reserves.id', '=', 'claim_reserves_coverages.id')
            ->where('claims.claim_type','Accident')
            ->select('claims.claim_number','claims.policy_id','claims.customer_id','claims.id','claims.claim_type','claims.status','claims.created_at','claims.updated_at','claim_reserves_coverages.coverage_id','claim_reserves.include_vat','claim_reserves.transaction_sub_type','claim_reserves.transaction_type')
            // ->orderBy('claim_reserves_coverages.claim_id', 'ASC');
            ->groupBy('claims.claim_number');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(claims.created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(claims.created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->addColumn('claim_no', function ($claim) {
                $claim_no = $claim->claim_number;
                return $claim_no;
            })
            ->addColumn('policy_number', function ($claim) {
                $policy = Policy::where('id',$claim->policy_id)->first(array('policyNumber'));
                if($policy)
                {
                    $policy_number = $policy->policyNumber;
                }
                else{
                    $policy_number = 'NA';
                }
                return $policy_number;
            })
            ->addColumn('product_name', function ($claim) {
                $policy = Policy::where('id',$claim->policy_id)->first();
                if($policy)
                {
                    $product = Product::where('id',$policy->product_id)->first(array('name'));
                    if($product)
                    {
                        $product_name = $product->name;
                    }
                    else
                    {
                        $product_name = 'NA';
                    }
                }
                else
                {
                    $product_name = 'NA';
                }
                return $product_name;
            })
            ->editColumn('insured_name', function ($claim) {
                $customer = Customer::where('id',$claim->customer_id)->first();
                if($customer)
                {
                    $insured_name = $customer->firstName.' '.$customer->lastName;
                }
                else
                {
                    $insured_name = 'NA';
                }
                return $insured_name;
            })
            ->addColumn('reported_date', function ($claim) {
                $reported_date = Carbon::parse($claim->created_at)->format('d-m-Y');
                return $reported_date;
            })
            ->addColumn('date_of_loss', function ($claim) {
                $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first(array('date_of_accident'));
                if($claim_accident)
                {
                    $date_of_loss = Carbon::parse($claim_accident->date_of_accident)->format('d-m-Y');
                }
                else
                {
                    $date_of_loss = 'NA';
                }
                return $date_of_loss;
            })
            ->addColumn('claim_type', function ($claim) {
                $claim_type = $claim->claim_type;
                return $claim_type;
            })
            ->addColumn('payment_date', function ($claim) {
                if($claim->status == 'Approved')
                {
                    return Carbon::parse($claim->updated_at)->format('d-m-Y');
                }
            })
            ->addColumn('transaction_type', function ($claim) {
                $transactionType = Lookup::where('id', $claim->transaction_type)->first('value');
                return $transactionType->value;

            })
            ->addColumn('trans_sub_type', function ($claim) {
                $transactionType = Lookup::where('id', $claim->transaction_sub_type)->first('value');
                if($transactionType != NULL)
                    return $transactionType->value;
            })
            ->addColumn('reserve_payment_amt', function ($claim) {
                $transactionType = Lookup::where('id', $claim->transaction_type)->first('value');
                $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
                if($claim_accident)
                {
                    if($transactionType->value == 'Lost')
                    {
                        $n = '-';
                        $reserve_payment_amt = $n.$claim_accident->reserve_amount;
                    }
                    else
                    {
                        $reserve_payment_amt = $claim_accident->reserve_amount;
                    }

                }
                else
                {
                    $reserve_payment_amt = 'NA';
                }
                return $reserve_payment_amt;
            })
            ->addColumn('coverage_name', function ($claim)
            {
                $claim = Coverage::where('id',$claim->coverage_id)->first(array('name'));
                if($claim)
                {
                    $coverag_name = $claim->name;
                }
                else
                {
                    $coverag_name = 'NA';
                }
                return $coverag_name;

            })
            ->addColumn('rserve_or_payment_amt', function ($claim) {
                $rserve_or_payment_amt = $claim->reserve_amt;
                return $rserve_or_payment_amt;
            })
            ->addColumn('motor_desc', function ($claim) {
                $vehicle = Vehicle::where('policy_id',$claim->policy_id)->first();
                if($vehicle)
                {
                    $motor_desc = $vehicle->vehiclePlate;
                }
                else
                {
                    $motor_desc = 'NA';
                }
                return $motor_desc;
            })
            ->addColumn('motor_desc2', function ($claim) {
                $vehicle = Vehicle::where('policy_id',$claim->policy_id)->first();
                if($vehicle)
                {
                    $motor_desc2 = $vehicle->make.' '.$vehicle->model;
                }
                else
                {
                    $motor_desc2 = 'NA';
                }
                return $motor_desc2;
            })
            ->addColumn('term_start_date', function ($claim) {
                $policy = Policy::where('id',$claim->policy_id)->first();
                if($policy)
                {
                    $term_start_date = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                }
                else
                {
                    $term_start_date = 'NA';
                }
                return $term_start_date;
            })
            ->addColumn('term_end_date', function ($claim) {
                $policy = Policy::where('id',$claim->policy_id)->first();
                if($policy)
                {
                    if($policy->premium_freq > 1 )
                    {
                        $term_end_date = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
                    } else {
                        $term_end_date = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
                    }
                }
                else
                {
                    $term_end_date = 'NA';
                }
                return $term_end_date;
            })
            ->addColumn('vat_include', function ($claim) {
                if($claim->vat_include == 1)
                    return 'YES';
                else
                    return 'NO';
            })
            ->rawColumns(['VATInclude','TermEndDate'])
            ->make(true);
    }
    public function reInsuranceClaimsprofileExport(Request $request){
        try{
            return Excel::download(new ReInsuranceClaimsprofileExport($request->all()), 'ReInsuranceClaimsprofileExport.xlsx');
        }catch(Exception $e){

        }
    }

    public function claimAsOnDateData(Request $request)
    {
//        return response()->json('test',401);
        $claim = Claim::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($claim)
            ->addColumn('claim_no', function ($claim) {
                $claim_no = 'NA';
                if($claim->id !=null){
                    $claim_no = $claim->claim_number;
                }
                return $claim_no;
            })
            ->addColumn('policy', function ($claim) {
                $policy_number = 'NA';
                if($claim->id !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    if($policy)
                    {
                        $policy_number = $policy->policyNumber;
                    }
                    else{
                        $policy_number = 'NA';
                    }
                }
                else
                {
                    $policy_number = 'NA';
                }
                return $policy_number;
            })
            ->addColumn('product_name', function ($claim) {
                $product_name= 'NA';
                if($claim->id !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    if($policy)
                    {
                        $product = Product::where('id',$policy->product_id)->first();
                        if($product)
                        {
                            $product_name = $product->name;
                        }
                        else
                        {
                            $product_name = 'NA';
                        }
                    }
                    else
                    {
                        $product_name = 'NA';
                    }
                }
                else
                {
                    $product_name = 'NA';
                }
                return $product_name;
            })
            ->editColumn('insured_name', function ($claim) {
                $insured_name = 'NA';
                if($claim->id !=null){
                    $customer = Customer::where('id',$claim->customer_id)->first();
                    if($customer)
                    {
                        $insured_name = $customer->firstName;
                    }
                    else
                    {
                        $insured_name = 'NA';
                    }
                }
                else
                {
                    $insured_name = 'NA';
                }
                return $insured_name;
            })
            ->editColumn('county_code', function ($claim) {
                $county_code = 'NA';
                if($claim->id !=null){
                    $county_code = '-';
                }
                return $county_code;
            })
            ->addColumn('date_of_loss', function ($claim) {
                $date_of_loss = 'NA';
                if($claim->id !=null){
                    $claim_key_loss = ClaimKeyLoss::where('claim_id',$claim->id)->first();
                    if($claim_key_loss)
                    {
                        $date_of_loss = $claim_key_loss->date_of_loss;
                    }
                    else
                    {
                        $date_of_loss = 'NA';
                    }
                }
                else
                {
                    $date_of_loss = 'NA';
                }
                return $date_of_loss;
            })

            ->addColumn('claim_status', function ($claim) {
                $claim_status = 'NA';
                if($claim->id !=null){
                    $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
                    if($claim_accident)
                    {
                        $claim_status = $claim_accident->claim_status;
                    }
                    else{
                        $claim_status = 'NA';
                    }
                }
                else{
                    $claim_status = 'NA';
                }
                return $claim_status;
            })
            ->addColumn('status_date', function ($claim) {
                $status_date = 'NA';
                if($claim->id !=null){
                    $status_date = $claim->created_at;
                }
                return $status_date;
            })
            ->addColumn('att_involved', function ($claim) {
                $att_involved = 'NA';
                if($claim->id !=null){
                    $att_involved = '-';
                }
                return $att_involved;
            })
            ->addColumn('pa_involved', function ($claim) {
                $pa_involved = 'NA';
                if($claim->id !=null){
                    $pa_involved = '-';
                }
                return $pa_involved;
            })
            ->addColumn('short_desc', function ($claim) {
                $short_desc = 'NA';
                if($claim->id !=null){
                    $short_desc = '-';
                }
                return $short_desc;
            })
            ->addColumn('reported_date', function ($claim) {
                $reported_date = 'NA';
                if($claim->id !=null){
                    $reported_date = '-';
                }
                return $reported_date;
            })
            ->addColumn('max_trans', function ($claim) {
                $max_trans = 'NA';
                if($claim->id !=null){
                    $max_trans = '-';
                }
                return $max_trans;
            })
            ->addColumn('claim_sub_status_na', function ($claim) {
                $claim_sub_status_na = 'NA';
                if($claim->id !=null){
                    $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
                    if($claim_accident)
                    {
                        $claim_sub_status_na = $claim_accident->claim_sub_status;
                    }
                    else
                    {
                        $claim_sub_status_na = 'NA';
                    }
                }
                else
                {
                    $claim_sub_status_na = 'NA';
                }
                return $claim_sub_status_na;
            })
            ->addColumn('total_reserve', function ($claim) {
                $total_reserve = 'NA';
                if($claim->id !=null){
                    $total_reserve = '-';
                }
                return $total_reserve;
            })
            ->addColumn('total_payment', function ($claim) {
                $total_payment = 'NA';
                if($claim->id !=null){
                    $total_payment = '-';
                }
                return $total_payment;
            })
            ->addColumn('balance', function ($claim) {
                $balance = 'NA';
                if($claim->id !=null){
                    $balance = '-';
                }
                return $balance;
            })
            ->addColumn('loss_rpt_attach_y_or_n', function ($claim) {
                $loss_rpt_attach_y_or_n = 'NA';
                if($claim->id !=null){
                    $loss_rpt_attach_y_or_n = '-';
                }
                return $loss_rpt_attach_y_or_n;
            })
            ->addColumn('salvage_subrogation_reserve', function ($claim) {
                $salvage_subrogation_reserve = 'NA';
                if($claim->id !=null){
                    $salvage_subrogation_reserve = '-';
                }
                return $salvage_subrogation_reserve;
            })
            ->addColumn('salvage_subrogation_payment', function ($claim) {
                $salvage_subrogation_payment = 'NA';
                if($claim->id !=null){
                    $salvage_subrogation_payment = '-';
                }
                return $salvage_subrogation_payment;
            })

            ->addColumn('claims_allocated_to', function ($claim) {
                $claims_allocated_to = 'NA';
                if($claim->id !=null){
                    $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
                    if($claim_accident)
                    {
                        $claims_allocated_to = $claim_accident->claim_allocated_to;
                    }
                    else
                    {
                        $claims_allocated_to = 'NA';
                    }
                }
                else
                {
                    $claims_allocated_to = 'NA';
                }
                return $claims_allocated_to;
            })
            ->rawColumns(['claims_allocated_to','salvage_subrogation_payment'])
            ->make(true);
    }

    public function subLedgerReportData(Request $request)
    {
        $policyLedger = Ledger::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policyLedger->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policyLedger->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($policyLedger)
            ->addColumn('transaction_no', function ($policyLedger) {
                $transaction_no = 'NA';
                if($policyLedger->id !=null){
                    $transaction_no = '-';
                }
                return $transaction_no;
            })
            ->addColumn('policy_no', function ($policyLedger) {
                $policy_no = 'NA';
                if($policyLedger->id !=null){
                    $policy = Policy::where('id',$policyLedger->policy_id)->first();
                    if($policy)
                    {
                        $policy_no = $policy->policyNumber;
                    }
                    else
                    {
                        $policy_no = 'NA';
                    }
                }
                else
                {
                    $policy_no = 'NA';
                }
                return $policy_no;
            })
            ->addColumn('booking_date', function ($policyLedger) {
                $booking_date = 'NA';
                if($policyLedger->id !=null){
                    $booking_date = '-';
                }
                return $booking_date;
            })
            ->addColumn('accounting_date', function ($policyLedger) {
                $accounting_date = 'NA';
                if($policyLedger->id !=null){
                    $accounting_date = $policyLedger->accounting_date;
                }
                return $accounting_date;
            })
            ->addColumn('trans_ref_no', function ($policyLedger) {
                $trans_ref_no = 'NA';
                if($policyLedger->id !=null){
                    $trans_ref_no = $policyLedger->trans_ref;
                }
                return $trans_ref_no;
            })
            ->addColumn('action', function ($policyLedger) {
                $action = 'NA';
                if($policyLedger->id !=null){
                    $action = '-';
                }
                return $action;
            })
            ->addColumn('subAccount_name', function ($policyLedger) {
                $subAccount_name = 'NA';
                if($policyLedger->id !=null){
                    $subAccount_name = '-';
                }
                return $subAccount_name;
            })
            ->addColumn('debit_amount', function ($policyLedger) {
                $debit_amount = 'NA';
                if($policyLedger->id !=null){
                    $debit_amount = $policyLedger->debit;
                }
                return $debit_amount;
            })
            ->addColumn('credit_amount', function ($policyLedger) {
                $credit_amount = 'NA';
                if($policyLedger->id !=null){
                    $credit_amount = $policyLedger->credit;
                }
                return $credit_amount;
            })
            ->addColumn('memo', function ($policyLedger) {
                $memo = 'NA';
                if($policyLedger->id !=null){
                    $memo = '-';
                }
                return $memo;
            })
            ->addColumn('check_no', function ($policyLedger) {
                $check_no = 'NA';
                if($policyLedger->id !=null){
                    $check_no = '-';
                }
                return $check_no;
            })
            ->addColumn('printedj_date', function ($policyLedger) {
                $printedj_date = 'NA';
                if($policyLedger->id !=null){
                    $printedj_date = '-';
                }
                return $printedj_date;
            })
            ->addColumn('posted_status', function ($policyLedger) {
                $posted_status = 'NA';
                if($policyLedger->id !=null){
                    $posted_status = '-';
                }
                return $posted_status;
            })
            ->addColumn('posted_date', function ($policyLedger) {
                $posted_date = 'NA';
                if($policyLedger->id !=null){
                    $posted_date = '-';
                }
                return $posted_date;
            })
            ->addColumn('posted_by', function ($policyLedger) {
                $posted_by = 'NA';
                if($policyLedger->id !=null){
                    $posted_by = '-';
                }
                return $posted_by;
            })
            ->addColumn('gl_trans_id', function ($policyLedger) {
                $gl_trans_id = 'NA';
                if($policyLedger->id !=null){
                    $gl_trans_id = '-';
                }
                return $gl_trans_id;
            })
            ->rawColumns([])
            ->make(true);
    }

    public function getPolicyStatusReport(Request $request)
    {
        $policy = Policy::query();
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        //        $policy = Policy::query();
        return DataTables::eloquent($policy)
            ->editColumn('policyNumber', function ($policy) {
                $policyNumber = 'NA';
                if($policy->policyNumber !=null){
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->editColumn('customer_id', function ($policy) {
                $cust_name = 'NA';
                if ($policy->customer_id != null)
                {
                    $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
                    if($customer != null) {
                        $cust_name = $customer->firstName.' '.$customer->lastName;
                    }
                    else {
                        $cust_name = 'N/A';
                    }
                }else{
                    $cust_name = 'N/A';
                }
                return $cust_name;
            })
            ->editColumn('billingStartDate', function ($policy) {
                if($policy->policyNumber !=null){
                    $billingStartDate = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                }
                return $billingStartDate;
            })
            ->addColumn('term_end_date', function ($policy) {
                $term_end_date= 'NA';

                    $pos = strpos($policy->billingStartDate, '/');
                if ($pos !== false) {
                    $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
                }
                if($policy->premium_freq == 1 || $policy->premium_freq == NULL)
                {
                    $term_end_date = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
                } else {
                    $term_end_date = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
                }

                return $term_end_date;
            })
            ->editColumn('created_at', function ($policy) {
                if($policy->policyNumber !=null){
                    $created_at = Carbon::parse($policy->created_at)->format('d-m-Y');
                }
                return $created_at;
            })
            ->addColumn('gross_premium', function ($policy) {
                $gross_premium = 'NA';
                if($policy->id !=null){
                    $gross_premium = $policy->premium;
                }
                return $gross_premium;
            })
            ->addColumn('net_premium', function ($policy) {
                $net_premium = 'NA';
                if($policy->id !=null){
                    $net_premium= (($policy->premium)-($policy->vat));
                }
                return $net_premium;
            })
            // ->addColumn('agent_name', function ($policy) {
            //     $agent_name = 'NA';
            //     if ($policy->customer_id != null)
            //     {
            //         $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
            //         if($customer != null) {
            //             $agent_name = $customer->firstName.' '.$customer->lastName;
            //         }
            //         else {
            //             $agent_name = 'N/A';
            //         }
            //     }else{
            //         $agent_name = 'N/A';
            //     }
            //     return $agent_name;
            // })
            ->editColumn('leadSource', function ($policy) {
                if($policy->policyNumber !=null){
                    $leadSource = $policy->leadSource;
                }
                return $leadSource;
            })
            ->rawColumns(['status','total','endorse','product_id_count','name'])
            ->make(true);
    }


    public function policyActivatedTodayReport(){
        return view('admin.reports.policyActivatedToday');
    }

    public function getPolicyActivatedTodayReport(Request $request)
    {
        $policy = PolicyActivateCancelledDate::join('policies','policies.policyNumber','policyactivatecancelleddates.policyNumber')
            ->join('customer','customer.id','policies.customer_id')
            ->join('products','products.id','policies.product_id')
            ->leftJoin('users','users.id','policies.agent_id')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('date(policyactivatecancelleddates.activated_date)') , [Carbon::parse('today')
                ->format('Y-m-d')  , Carbon::parse('today')
                ->format('Y-m-d') ])
            ->groupBy('policyactivatecancelleddates.policyNumber')
            ->orderBy('policyactivatecancelleddates.id','desc')
            ->get(
                array(
                    'policies.id',
                    'customer.id as customer_id',
                    'customer.firstName',
                    'customer.lastName',
                    'users.firstName as f_name',
                    'users.lastName as l_name',
                    'customer.middleName',
                    'customer.cellphone',
                    'customer.email',
                    'policies.policyNumber',
                    'policies.status',
                    'products.name as product_name',
                    'policyactivatecancelleddates.policyNumber',
                    'policyactivatecancelleddates.activated_date',
                    'policies.created_at as policy_created'
                )
            );
        return FacadesDataTables::of($policy)
            ->editColumn('policyNumber', function ($policy) {
                $policyNumber = 'NA';
                if($policy->policyNumber !=null){
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('customer_name', function ($policy) {
                if ($policy->customer_id != null)
                {
                    if($policy != null) {
                        $cust_name = $policy->firstName.' '.$policy->lastName;
                    }
                    else {
                        $cust_name = 'N/A';
                    }
                }else{
                    $cust_name = 'N/A';
                }
                return $cust_name;
            })
            ->addColumn('product', function ($policy) {
                if ($policy->product_name != null)
                {
                    $product_name = $policy->product_name;
                }else{
                    $product_name = 'N/A';
                }
                return $product_name;
            })
            ->addColumn('cellphone', function ($policy) {
                if ($policy->cellphone != null)
                {
                    $cellphone = $policy->cellphone;
                }else{
                    $cellphone = 'N/A';
                }
                return $cellphone;
            })
            ->addColumn('email', function ($policy) {
                if ($policy->email != null)
                {
                    $email = $policy->email;
                }else{
                    $email = 'N/A';
                }
                return $email;
            })
            ->editColumn('created_at', function ($policy) {
                if($policy->policyNumber !=null){
                    $created_at = Carbon::parse($policy->created_at)->format('d-m-Y');
                }
                return $created_at;
            })
            ->addColumn('agent_name', function ($policy) {
                if ($policy->f_name != null)
                {
                    $agent_name = $policy->f_name.' '.$policy->l_name;
                }else{
                    $agent_name = 'N/A';
                }
                return $agent_name;
            })
            ->make(true);
    }

    public function detailedAgeAnalysisDataExport(Request $request)
    {
        return Excel::download(new DetailedAnalysisReportExcel($request->all()), 'DetailedAgeAnalysisReport.xlsx');
    }
    public function summaryAgeAnalysisDataExport(Request $request)
    {
        return Excel::download(new SummeryAgeAnalysisReport($request->all()), 'DetailedAgeAnalysisReport.xlsx');
    }
    public function inforceReportExport(Request $request){
        try{
            return Excel::download(new InforceReportExport($request->all()), 'InforceReportExport.xlsx');
        }catch(Exception $e){

        }
    }
    public function writtenPremiumReportExport(Request $request){
        try{
            return Excel::download(new WrittenPremiumReportExport($request->all()), 'WrittenPremiumReportExport.xlsx');
        }catch(Exception $e){

        }
    }
    public function claimAsOnDateDataExport(Request $request){
        try{
            return Excel::download(new ClaimAsOnDateDataExport($request->all()), 'ClaimAsOnDateDataExport.xlsx');
        }catch(Exception $e){

        }
    }
    public function subLedgerReportExport(Request $request){
        try{
            return Excel::download(new SubLedgerReportExport($request->all()), 'SubLedgerReportExport.xlsx');
        }catch(Exception $e){

        }
    }
    public function policyStatusReportExport(Request $request)
    {
        return Excel::download(new PolicyStatusReportExport($request->all()), 'PolicyStatusReport.xlsx');
    }

    public function summaryAgeAnalysis(){
        return view('admin.reports.summaryAnalysisReport');
    }

    public function summaryAgeAnalysisData(){
        $data = SummaryAgeAnalysisReport::query();
        return DataTables::eloquent($data)
            ->editColumn('policyNumber', function ($policy) {
                return isset($policy->policyNumber)?$policy->policyNumber:'';
            })
            ->editColumn('customer_name', function ($policy) {

                return isset($policy->client_name)?$policy->client_name:'';
            })
            ->editColumn('balance', function ($policy) {

                return isset($policy->balance_outstanding)?$policy->balance_outstanding:'';
            })
            ->editColumn('30_days', function ($policy) {

                return isset($policy['30_days'])?$policy['30_days']:'';

            })
            ->editColumn('60_days', function ($policy) {
                return isset($policy['60_days'])?$policy['60_days']:'';

            })
            ->editColumn('90_days', function ($policy) {
                return isset($policy['90_days'])?$policy['90_days']:'';

            })
            ->editColumn('120_days_above', function ($policy) {
                return isset($policy['120_days_and_above'])?$policy['120_days_and_above']:'';

            })
//            ->rawColumns(['status'])
            ->make(true);
    }

    public function summaryAgeAnalystReportDump(){
        ini_set('max_execution_time', 0);
        $records = Policy::select('id','policyNumber','customer_id')->get()->chunk(30);
        foreach($records as $record){
            $arr = [];
            foreach($record as $policy){
                $findPolicy  = SummaryAgeAnalysisReport::findByPolicy($policy->id)->first();
                $policyNumber = '';
                if($policy->policyNumber !=null){
                    $policyNumber = $policy->policyNumber;
                }

                $customer = Customer::where('id',$policy->customer_id)->first();
                $name = '';
                if($customer){
                    $name = $customer->firstName.' '.$customer->lastName;
                }
                $ledger = Ledger::findledgerbypolicyid($policy->id)->first();
                $balance = '';
                if(!empty($ledger)){
                    if($ledger->balance != ''){
                        if($ledger->balance < 0){
                            $balance = str_replace('-','',$ledger->balance);

                        }
                    }
                }

                $days_30 = '';
                $date_before_30 = Carbon::now()->subDays(30);
                $todays_date = Carbon::now();
                $diff = Ledger::findledgerbypolicyid($policy->id)->calculateAmountByDate($date_before_30,$todays_date);
                if($diff){

                    $days_30 = str_replace('-','',$diff);
                }


                $days_60 = '';
                $todays_date = Carbon::now()->subDays(30);
                $date_before_60 = Carbon::now()->subDays(60);
                $diff = Ledger::findledgerbypolicyid($policy->id)->calculateAmountByDate($date_before_60,$todays_date);
                if($diff){
                    $days_60 = str_replace('-','',$diff);
                }

                $days_90 = '';
                $todays_date = Carbon::now()->subDays(60);
                $date_before_90 = Carbon::now()->subDays(90);
                $diff = Ledger::findledgerbypolicyid($policy->id)->calculateAmountByDate($date_before_90,$todays_date);
                if($diff){
                    $days_90 = str_replace('-','',$diff);
                }


                $days_120 = '';
                $date_before_120 = Carbon::now()->subDays(90);
                $diff = Ledger::findledgerbypolicyid($policy->id)
                    ->where('accounting_date','<',$date_before_120)
                    ->sum('invoice_amount');
                if($diff){
                    $days_120 = str_replace('-','',$diff);
                }
                $data = array();
                $data['policy_id'] = $policy->id;
                $data['policyNumber'] = $policyNumber;
                $data['client_name'] = $name;
                $data['balance_outstanding'] = $balance;
                $data['30_days'] = $days_30;
                $data['60_days'] = $days_60;
                $data['90_days'] = $days_90;
                $data['120_days_and_above'] = $days_120;
                if($findPolicy){
                    $findPolicy->update($data);
                }else{
                    array_push($arr,$data);
                }
            }
            DB::table('summary_age_analyst_report')->insert($arr);
        }

        return 'success';
    }

    public function transactionReportByProduct(Request $request)
    {
        return view('admin.reports.transaction_Report_By_Product');
    }

    public function transactionReportByProductExport(Request $request){
        try{
            return Excel::download(new TransactionReportByProductExcel($request->all()), 'Transaction Report By Product Excel.xlsx');
        }catch(Exception $e){

        }
    }
    public function transactionReportByProductdata(Request $request)
    {
        $policyLedger = Ledger::join('policies', 'policies.id', '=', 'policy_ledger.policy_id')
        ->join('customer', 'customer.id' , '=', 'policy_ledger.customer_id')
        ->select('policies.policyNumber','policies.premium_freq','customer.firstName','customer.lastName','policy_ledger.trans_sub_type','policy_ledger.claim_id','policies.note','policies.status','policies.updated_at','policy_ledger.trans_type','policy_ledger.invoice_no','policies.customer_id','policies.created_at')
        ->orderBy('policy_ledger.created_at', 'DESC')->distinct('policies.policyNumber');
    if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
    {
        $policyLedger->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse(request('filterDateFrom'))
            ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
            ->format('Y-m-d') ]);
    }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
        //Carbon::parse('today')
        $policyLedger->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse(request('filterDateFrom'))
            ->format('Y-m-d') , Carbon::parse('today')
            ->format('Y-m-d') ]);
    }

    return DataTables::eloquent($policyLedger)
        ->addColumn('customer_name', function ($policyLedger) {
            return $cust_name = ucwords(strtolower($policyLedger->firstName)).' '.ucwords(strtolower($policyLedger->lastNames));
        })
        ->addColumn('premium_freq', function ($policyLedger) {
            if($policyLedger->premium_freq == 1) {
                $freq = 'Monthly';
            }
            elseif($policyLedger->premium_freq == 2) {
                $freq = 'Three Instalsments';
            }else{
                $freq = 'Annual';
            }
            return $freq;
        })
        ->addColumn('service_rep', function ($policyLedger) {
            return $cust_name = ucwords(strtolower($policyLedger->firstName)).' '.ucwords(strtolower($policyLedger->lastNames));
        })
        ->addColumn('address', function ($policyLedger) {
            $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('address'));
            if($customer)
            {
                $address = $customer->address;
            }
            else
            {
                $address = 'NA';
            }

            return $address;
        })
        ->addColumn('country', function ($policyLedger) {
            $country = 'NA';
            if($policyLedger){
                $CustomerProfile = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('countryId'));
                if($policyLedger){
                    $country = Country::where('id',$CustomerProfile->countryId)->first(array('name'));
                    if($country){
                        $country = $country->name;
                    }
                    else{
                        $country = 'Botswana';
                    }

                }
            }
            return $country;
        })
        ->addColumn('city', function ($policyLedger) {
            $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('city'));
            if($customer)
            {
                $city = $customer->city;
            }
            else
            {
                $city = 'NA';
            }

            return $city;
        })
        ->addColumn('state', function ($policyLedger) {
            $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('state'));
            if($customer)
            {
                $state = State::where('id',$customer->state)->first(array('id','name'));
                if($state)
                {
                    $state = $state->name;
                }

            }
            else
            {
                $state = 'NA';
            }

            return $state;
        })
        ->addColumn('cov_A', function ($policyLedger) {
            $cov_A = '-';
            if($policyLedger->id !=null){
                $cov_A = '-';
            }
            return $cov_A;
        })
        ->addColumn('term_start', function ($policyLedger) {
            $pos = strpos($policyLedger->billingStartDate, '/');
            if ($pos !== false) {
                $term_start = Carbon::createFromFormat('d/m/Y', $policyLedger->billingStartDate)->format('d-m-Y');
            } else {
                $term_start = Carbon::parse($policyLedger->billingStartDate)->format('d-m-Y');
            }
            return $term_start;
        })
        ->addColumn('term_end', function ($policyLedger) {
            $pos = strpos($policyLedger->billingStartDate, '/');
            if ($pos !== false) {
                $policyLedger->billingStartDate = Carbon::createFromFormat('d/m/Y', $policyLedger->billingStartDate)->format('d-m-Y');
            }
            if($policyLedger->premium_freq > 1 )
            {
                $term_end = Carbon::parse($policyLedger->billingStartDate)->addYear()->subDay()->format('d-m-Y');

            } else {
                $term_end = Carbon::parse($policyLedger->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
            }

            return $term_end;
        })
        ->addColumn('act_date', function ($policyLedger) {
            $act_date = 'N\A';
            if($policyLedger->id !=null){
                $act_date = '-';
            }
            return $act_date;
        })
        ->editColumn('trans_type', function ($policyLedger) {
            $pos = strpos($policyLedger->created_at, '/');
            if ($pos !== false) {
                $years = Carbon::createFromFormat('d/m/Y', $policyLedger->created_at)->format('d-m-Y');
            } else {
                $years = Carbon::parse($policyLedger->created_at)->diffInYears(Carbon::now());
                if ($years == 0){
                    return "New Business";
                }else{
                    return "Renew";
                }
            }

        })
        ->editColumn('trans_sub_type', function ($policyLedger) {
            $invoice_no = substr($policyLedger->invoice_no, strpos($policyLedger->invoice_no, "-") + 1);
                if ($invoice_no == '001')
                {
                    $trans_sub_type  = 'Agent Business';
                }
                elseif ($invoice_no != '001')
                {
                    $trans_sub_type  = 'Renewal';
                }
                else{
                    $trans_sub_type  = 'NonPay';
                }
                return $trans_sub_type;

        })
        ->addColumn('status', function ($policyLedger) {
            if($policyLedger->status == 1) {
                $status = 'Yes';
            }
            else{
                $status = 'No';
            }
            return $status;
        })
        ->addColumn('pre_change', function ($policyLedger) {
            $pre_change = 'N\A';
            if($policyLedger->id !=null){
                $pre_change = '-';
            }
            return $pre_change;
        })
        ->addColumn('updated_at', function ($policyLedger) {
            $pos = strpos($policyLedger->billingStartDate, '/');
            if ($pos !== false) {
                $updated_at = Carbon::createFromFormat('d/m/Y', $policyLedger->updated_at)->format('d-m-Y');
            } else {
                $updated_at = Carbon::parse($policyLedger->updated_at)->format('d-m-Y');
            }
            return $updated_at;
        })
        ->addColumn('note', function ($policyLedger) {
            return $note = $policyLedger->note;
        })
        ->addColumn('anniversary_end', function ($policyLedger) {
            $anniversary_end = 'NA';
            if($policyLedger){
                $anniversary_end = Carbon::parse($policyLedger->created_at)->addYear()->subDay()->format('d-m-Y');
            }
            return $anniversary_end;
        })
        ->addColumn('anniversary_seque', function ($policyLedger) {
            $anniversary_seque = '- ';
            if($policyLedger->id !=null){
                $anniversary_seque= '-';
            }
            return $anniversary_seque;
        })
        ->addColumn('termresetcounter', function ($policyLedger) {
            $termresetcounter = 'N\A';
            if($policyLedger->id !=null){
                $termresetcounter= '-';
            }
            return $termresetcounter;
        })
        ->rawColumns([])
        ->make(true);
    }

    public function agentReport()
    {
        return view('admin.reports.agent_reports');
    }

    public function agentReportExport(Request $request){
        try{
            return Excel::download(new AgentReportExport($request->all()), 'AgentReportExport.xlsx');
        }catch(Exception $e){

        }
    }

    public function agentReportData(Request $request)
    {
        $userProfile = UserProfile::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $userProfile->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $userProfile->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }



//        $policy = Policy::query();
        return DataTables::eloquent($userProfile)
            ->editColumn('agency_name', function ($userProfile) {
                $agency_name = 'NA';
                if($userProfile->id !=null){
                    $agency_name = '-';
                }
                return $agency_name;
            })
            ->addColumn('account_id', function ($userProfile) {
                $account_id = 'NA';
                if($userProfile->id !=null) {
                    $account_id = '-';
                }
                return $account_id;
            })
            ->editColumn('cellphone', function ($userProfile) {
                $cellphone = 'N\A';
                if($userProfile->id !=null){
                        $cellphone = $userProfile->cellphone;
                }
                return $cellphone;
            })
            ->addColumn('fax_no', function ($userProfile) {
                $fax_no = 'N\A';
                if($userProfile->id !=null){
                    $fax_no = '-';
                }
                return $fax_no;
            })
            ->addColumn('principal_name', function ($userProfile) {
                $principal_name = 'N\A';
                if($userProfile->id !=null){
                    $user = User::where('id',$userProfile->user_id)->first();
                    $principal_name = $user->firstName;
                }
                return $principal_name;
            })
            ->addColumn('principal_email', function ($userProfile) {
                $principal_email = 'N\A';
                if($userProfile->id !=null){
                    $user = User::where('id',$userProfile->user_id)->first();
                    $principal_email = $user->email;
                }
                return $principal_email;
            })
            ->addColumn('product', function ($userProfile) {
                $product = 'N\A';
                if($userProfile->id !=null){
                    $product = '-';
                }
                return $product;
            })
            ->addColumn('underwriter', function ($userProfile) {
                $underwriter = 'N\A';
                if($userProfile->id !=null){
                    $underwriter = '-';
                }
                return $underwriter;
            })
            ->addColumn('service_rep', function ($userProfile) {
                $service_rep = 'N\A';
                if($userProfile->id !=null){
                    $service_rep = '-';
                }
                return $service_rep;
            })
            ->addColumn('user_name', function ($userProfile) {
                $user_name = 'N\A';
                if($userProfile->id !=null){
                    $user = User::where('id',$userProfile->user_id)->first();
                    $user_name = $user->email;
                }
                return $user_name;
            })
            ->addColumn('user_id', function ($userProfile) {
                $user_id = 'N\A';
                if($userProfile->id !=null){
                    $user_id = $userProfile->user_id;
                }
                return $user_id;
            })
            ->addColumn('agent_name', function ($userProfile) {
                $agent_name = 'N\A';
                if($userProfile->id !=null){
                    $agent_name = '-';
                }
                return $agent_name;
            })
            ->addColumn('agent_email', function ($userProfile) {
                $agent_email = 'N\A';
                if($userProfile->id !=null){
                    $agent_email = '-';
                }
                return $agent_email;
            })
            ->addColumn('user_status', function ($userProfile) {
                $user_status = 'N\A';
                if($userProfile->id !=null){
                    $user = User::where('id',$userProfile->user_id)->first();
                    if($user->active == 1)
                    $user_status = 'Active';
                }
                return $user_status;
            })
            ->addColumn('user_type', function ($userProfile) {
                $user_type = 'N\A';
                if($userProfile->id !=null){
                        $user_type = '-';
                }
                return $user_type;
            })
            ->addColumn('s_address_line1', function ($userProfile) {
                $s_address_line1 = 'N\A';
                if($userProfile->id !=null){
                    $s_address_line1 = $userProfile->address;
                }
                return $s_address_line1;
            })
            ->addColumn('address_line2', function ($userProfile) {
                $address_line2 = 'N\A';
                if($userProfile->id !=null){
                    $address_line2 = '-';
                }
                return $address_line2;
            })
            ->addColumn('city', function ($userProfile) {
                $city = 'N\A';
                if($userProfile->id !=null){
                    $city = '-';
                }
                return $city;
            })
            ->addColumn('county', function ($userProfile) {
                $county = 'N\A';
                if($userProfile->id !=null){
                    $county = '-';
                }
                return $county;
            })
            ->addColumn('postal_code', function ($userProfile) {
                $postal_code = 'N\A';
                if($userProfile->id !=null){
                    $postal_code = '-';
                }
                return $postal_code;
            })
            ->addColumn('auth_code', function ($userProfile) {
                $auth_code = 'N\A';
                if($userProfile->id !=null){
                    $auth_code = '-';
                }
                return $auth_code;
            })
            ->addColumn('s_license_no', function ($userProfile) {
                $s_license_no = 'N\A';
                if($userProfile->id !=null){
                    $s_license_no = '-';
                }
                return $s_license_no;
            })

            ->rawColumns(['s_license_no','auth_code','postal_code','county','city','address_line2','s_address_line1','user_type','user_status','agent_email','agent_name','user_id','user_name','underwriter','product','principal_email','principal_name','fax_no','cellphone','account_id','agency_name'])
            ->make(true);
    }

    public function anniversaryDateReport()
    {
        return view('admin.reports.anniversary_date_report');
    }

    public function anniversaryDateReportExport(Request $request){
        try{
            return Excel::download(new AnniversaryDateReprotExport($request->all()), 'AnniversaryDateReprotExport.xlsx');
        }catch(Exception $e){

        }
    }

    public function anniversaryDateReportData(Request $request)
    {
        $policy = Policy::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $policy->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }

        return DataTables::eloquent($policy)
            ->editColumn('policyNumber', function ($policy) {
                $policyNumber = 'NA';
                if($policy->id !=null){
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('n_term_master_pk', function ($policy) {
                $n_term_master_pk = 'NA';
                if($policy->id !=null) {
                    $n_term_master_pk = '-';
                }
                return $n_term_master_pk;
            })
            ->addColumn('n_policy_master_fk', function ($policy) {
                $n_policy_master_fk = 'NA';
                if($policy->id !=null) {
                    $n_policy_master_fk = '-';
                }
                return $n_policy_master_fk;
            })
            ->addColumn('d_term_start_date', function ($policy) {
                $d_term_start_date = 'NA';
                if($policy->id !=null) {
                    $d_term_start_date = $policy->created_at;
                }
                return $d_term_start_date;
            })
            ->addColumn('d_term_end_date', function ($policy) {
                $d_term_end_date = 'NA';
                if($policy->id !=null) {
                    $d_term_end_date = '-';
                }
                return $d_term_end_date;
            })
            ->addColumn('n_term_last_tran_fk', function ($policy) {
                $n_term_last_tran_fk = 'NA';
                if($policy->id !=null) {
                    $n_term_last_tran_fk = '-';
                }
                return $n_term_last_tran_fk;
            })
            ->addColumn('for_monthly', function ($policy) {
                $for_monthly = 'NA';
                if($policy->id !=null) {
                    $for_monthly = '-';
                }
                return $for_monthly;
            })
            ->addColumn('n_term_sequence', function ($policy) {
                $n_term_sequence = 'NA';
                if($policy->id !=null) {
                    $n_term_sequence = '-';
                }
                return $n_term_sequence;
            })
            ->addColumn('anniversary_start', function ($policy) {
                $anniversary_start = 'NA';
                if($policy->id !=null) {
                    $anniversary_start = '-';
                }
                return $anniversary_start;
            })
            ->addColumn('anniversary_end_date', function ($policy) {
                $anniversary_end_date = 'NA';
                if($policy->id !=null) {
                    $anniversary_end_date = '-';
                }
                return $anniversary_end_date;
            })
            ->addColumn('n_anniversary_sequen', function ($policy) {
                $n_anniversary_sequen = 'NA';
                if($policy->id !=null) {
                    $n_anniversary_sequen = '-';
                }
                return $n_anniversary_sequen;
            })
            ->addColumn('n_term_reset_counter', function ($policy) {
                $n_term_reset_counter = 'NA';
                if($policy->id !=null) {
                    $n_term_reset_counter = '-';
                }
                return $n_term_reset_counter;
            })
            ->rawColumns(['policyNumber'])
            ->make(true);
    }

    public function reinsuranceRiskProfilesSummary()
    {
        $fromDate = '2020-04-01';
        $toDate = '2021-03-31';
        $life = array();
        $life['policy_count'] = Policy::where('product_id', 1)->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $life['premium'] = Policy::where('product_id', 1)->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $claims = Claim::where('claim_type', 'Life')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        if($claims == NULL)
        {
            $life['claim_count'] = 0;
            $life['payment'] = 0;
            $life['reserve'] = 0;
        } else {
            $life['claim_count'] = $claims->count();
            $life['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $life['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        $vehicle = array();
        $vehicle[0]['risk_band'] = '0 to 250 000';
        $vehicle[0]['policy_count'] = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $vehicle[0]['premium'] = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $vehicle[0]['sum_assured'] = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [2])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[0]['claim_count'] = 0;
            $vehicle[0]['payment'] = 0;
            $vehicle[0]['reserve'] = 0;
        } else {
            $vehicle[0]['claim_count'] = $claims->count();
            $vehicle[0]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[0]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $vehicle[1]['risk_band'] = '250 001 - 500 000';
        $vehicle[1]['policy_count'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $vehicle[1]['premium'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $vehicle[1]['sum_assured'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[1]['claim_count'] = 0;
            $vehicle[1]['payment'] = 0;
            $vehicle[1]['reserve'] = 0;
        } else {
            $vehicle[1]['claim_count'] = $claims->count();
            $vehicle[1]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[1]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $vehicle[2]['risk_band'] = '500 000 +';
        $vehicle[2]['policy_count'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $vehicle[2]['premium'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $vehicle[2]['sum_assured'] = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [2])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[2]['claim_count'] = 0;
            $vehicle[2]['payment'] = 0;
            $vehicle[2]['reserve'] = 0;
        } else {
            $vehicle[2]['claim_count'] = $claims->count();
            $vehicle[2]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[2]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        $motorComp = array();
        $motorComp[0]['risk_band'] = '0 to 250 000';
        $motorComp[0]['policy_count'] = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $motorComp[0]['premium'] = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $motorComp[0]['sum_assured'] = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[0]['claim_count'] = 0;
            $motorComp[0]['payment'] = 0;
            $motorComp[0]['reserve'] = 0;
        } else {
            $motorComp[0]['claim_count'] = $claims->count();
            $motorComp[0]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[0]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $motorComp[1]['risk_band'] = '250 001 - 500 000';
        $motorComp[1]['policy_count'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $motorComp[1]['premium'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $motorComp[1]['sum_assured'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[1]['claim_count'] = 0;
            $motorComp[1]['payment'] = 0;
            $motorComp[1]['reserve'] = 0;
        } else {
            $motorComp[1]['claim_count'] = $claims->count();
            $motorComp[1]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[1]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $motorComp[2]['risk_band'] = '500 000 +';
        $motorComp[2]['policy_count'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->count();
        $motorComp[2]['premium'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('premium');
        $motorComp[2]['sum_assured'] = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->sum('sum_assured');
        $policy = Policy::whereIn('product_id', [3])->where('sum_assured', '>', '500000')->whereBetween(DB::raw('date(created_at)') , [$fromDate , $toDate])->get(array('id'));
        $claims = Claim::whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[2]['claim_count'] = 0;
            $motorComp[2]['payment'] = 0;
            $motorComp[2]['reserve'] = 0;
        } else {
            $motorComp[2]['claim_count'] = $claims->count();
            $motorComp[2]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[2]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        return view('admin.reports.reinsuranceRiskProfilesSummary', compact('life', 'vehicle', 'motorComp'));
    }

    public function reinsuranceRiskProfilesSummaryExport()
    {
        try{
            return Excel::download(new ReinsuranceRiskProfilesSummaryExport(), 'Reinsurance Risk Profiles Summary Export.xlsx');
        }catch(Exception $e){

        }
    }

    /*Transaction Report By Booking Date Starts*/

    public function transactionReportByBookingDate(){
        return view('admin.reports.transaction_report_by_booking_date');
    }

    public function gettransactionReportByBookingData(Request $request)
    {
        $now = Carbon::now();
        $financialYearStart = Carbon::now();
        $financialYearStart->set('month', 7);
        $financialYearStart->set('day', 1);

        if($financialYearStart->greaterThan($now))
            $financialYearStart->subYear();

        $policyLedger = Ledger::query();
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $policyLedger->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }elseif ($request->filterDateFrom != '-1' && $request->filterDateto == '-1'){
            //Carbon::parse('today')
            $policyLedger->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($policyLedger)
            ->addColumn('transId', function ($policyLedger) {
                if ($policyLedger->customer_id != null)
                {
                    $policy = Policy::where('id',$policyLedger->policy_id)->first(array('policyNumber'));
                    $trans = Transaction::where('policyNumber',$policy->policyNumber)->first(array('referenceNumber'));
                    if($trans) {
                        $transId = $trans->referenceNumber;
                    }else {
                        $transId = 'NA';
                    }
                }else{
                    $transId = 'NA';
                }
                return $transId;
            })
            ->addColumn('policyNumber', function ($policyLedger) {
                if ($policyLedger->customer_id != null) {
                    $policy = Policy::where('id',$policyLedger->policy_id)->first();
                    if ($policy) {
                        $policyNumber = $policy->policyNumber;
                    } else {
                        $policyNumber = 'NA';
                    }
                } else {
                    $policyNumber = 'NA';
                }
                return $policyNumber;
            })
            ->editColumn('customer_name', function ($policyLedger) {
                if ($policyLedger->customer_id != null)
                {
                    $policy = Policy::where('id',$policyLedger->policy_id)->first(array('customer_id'));
                    if($policy){
                        $customer = Customer::where('id',$policy->customer_id)->first(array('firstName','lastName'));
                        if($customer ) {
                            $cust_name = $customer->firstName.' '.$customer->lastName;
                        }
                        else {
                            $cust_name = 'NA';
                        }
                    }else{
                        $cust_name = 'NA';
                    }
                }else{
                    $cust_name = 'NA';
                }
                return $cust_name;
            })
            ->addColumn('address', function ($policyLedger) {
                    if ($policyLedger->customer_id != null) {
                        $customer = CustomerProfile::where('customer_id', $policyLedger->customer_id)->first(array('address'));
                        if ($customer) {
                            $address = $customer->address;
                        } else {
                            $address = 'NA';
                        }
                    } else {
                        $address = 'NA';
                    }
                return $address;
            })

            ->addColumn('transaction', function ($policyLedger) use ($financialYearStart) {
                $policy = Policy::where('id', $policyLedger->policy_id)->first(array('created_at'));
                $pos = strpos($policy->created_at, '/');
                if ($pos !== false) {
                    $created_at = Carbon::createFromFormat('d/m/Y', $policy->created_at);
                } else {
                    $created_at = Carbon::parse($policy->created_at);
                    if($created_at->greaterThan($financialYearStart))
                        return "NEW BUSINESS";
                    else
                        return "RE ISSUE";
                }
            })
            ->editColumn('city', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy){
                    $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first();
                    if($customer){
                        $city = $customer->city;
                    }else{
                        $city = 'N/A';
                    }
                }else{
                    $city = 'N/A';
                }
                return $city;
            })
            ->editColumn('status', function ($policyLedger) {
                    $policy = Policy::where('id',$policyLedger->policy_id)->first();
                    if($policy->status == 0){
                        $status = "Inactive";
                    }elseif($policy->status == 1){
                        $status = "Active";
                    }else{
                        $status = "Cancel";
                    }
                return $status;
            })
            ->editColumn('term_start', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first(array('billingStartDate'));
                if($policy){
                    $pos = strpos($policy->billingStartDate, '/');
                    if ($pos !== false) {
                        $term_start = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
                    }
                    else {
                        $term_start = Carbon::parse($policy->billingStartDate)->format('d-m-Y');
                    }
                }
                return $term_start;
            })
            ->editColumn('term_end', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first(array('billingStartDate','premium_freq'));
                if($policy){
                    $pos = strpos($policy->billingStartDate, '/');
                    if ($pos !== false) {
                        $policy->billingStartDate = Carbon::createFromFormat('d/m/Y', $policy->billingStartDate)->format('d-m-Y');
                    }
                    if($policyLedger->premium_freq >1 )
                    {
                        $term_end = Carbon::parse($policy->billingStartDate)->addYear()->subDay()->format('d-m-Y');
                    } else {
                        $term_end = Carbon::parse($policy->billingStartDate)->addMonth()->subDay()->format('d-m-Y');
                    }
                }else{
                    $term_end = 'NA';
                }
                return $term_end;
            })

            ->editColumn('act', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first(array('policyActivatedDate'));
                if($policy){
                    $act = Carbon::parse($policy->policyActivatedDate)->format('d-m-Y');
                }else{
                    $act = 'NA';
                }
                return $act;
            })
            ->editColumn('booking', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy->policyNumber !=null){
                    $booking = '-';
                }else{
                    $booking = 'NA';
                }
                return $booking;
            })
            ->editColumn('inforce', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy->policyNumber !=null){
                    $inforce = $policy->premium;
                }else{
                    $inforce = 'NA';
                }
                return $inforce;
            })
            ->editColumn('premChang', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy->policyNumber !=null){
                    $premChang = '-';
                }else{
                    $premChang = 'NA';
                }
                return $premChang;
            })
            ->editColumn('createdDate', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy){
                    $createdDate = Carbon::parse($policy->created_at)->format('d-m-Y');
                }else{
                    $createdDate = 'NA';
                }
                return $createdDate;
            })
            ->editColumn('updatedDate', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy){
                    $updatedDate = Carbon::parse($policy->updated_at)->format('d-m-Y');
                }else{
                    $updatedDate = 'NA';
                }
                return $updatedDate;
            })
            ->editColumn('max', function ($policyLedger) {
                $policy = Policy::where('id',$policyLedger->policy_id)->first();
                if($policy){
                    $max = '-';
                }else{
                    $max = 'NA';
                }
                return $max;
            })
            ->rawColumns(['policy_status','estimated_value','kyc_status','payment_status', 'agent', 'premium', 'is_imported', 'rate','policyNumber','cellphone','name'])
            ->make(true);
    }

    public function transactionProductdataExport(Request $request){
    }

    public function transactionReportByBookingDateExport(Request $request){
        try{
            return Excel::download(new TransactionReportByBookingDateExport($request->all()), 'TransactionReportByBookingDate.xlsx');
        }catch(Exception $e){
        }
    }

    /*Transaction Report By Booking Date End*/
    /*Reinsurance Profile Report Starts*/

    public function reinsuranceProfileReport(Request $request){
        return view('admin.reports.reinsuranceProfileReport');
    }

    public function getreinsuranceProfileReport(Request $request)
    {
        $product = Product::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $product->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $product->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
//        $policy = Policy::query();
        return DataTables::eloquent($product)
            ->editColumn('name', function ($product) {
                $name = 'NA';
                if($product->id !=null){
                    $name = $product->name;
                }
                return $name;
            })
            ->addColumn('product_id_count', function ($product) {
                $product_id_count = 'NA';
                if($product->id !=null) {
                    $product_id_count = Policy::where('product_id',$product->id)->count();
                }
                return $product_id_count;
            })
            ->editColumn('status', function ($product) {
                $status = 'Deactive';
                if($product->id !=null){

                    if($product->status == 1){
                        $status = $product->status;
                    }
                }
                return $status;
            })
            ->addColumn('endorse', function ($product) {
                $endorse = 'N\A';
                if($product->id !=null){
                    $endorse = 0;
                }
                return $endorse;
            })
            ->addColumn('total', function ($product) {
                $total = 0;
                if($product->id !=null){
                    $product_id_count = Policy::where('product_id',$product->id)->count();
                    $status = $product->status;
                    $endorse = 0;
                    $total = $product_id_count + $status + $endorse;
                }
                return $total;
            })

            ->rawColumns(['status','total','endorse','product_id_count','name'])
            ->make(true);
    }
    /*Reinsurance Profile Report End*/

    public function claimPaymentBordereaux(Request $request){
        return view('admin.reports.claimPaymentBordereaux');
    }

    public function getclaimPaymentBordereaux(Request $request)
    {
        $claim = Claim::orderBy('created_at', 'ASC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->editColumn('claim_number', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimNumber = $claim->claim_number;
                }
                return $claimNumber;
            })
            ->addColumn('policyNumber', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('product_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $product = Product::where('id',$policy->product_id)->first();
                    $productName = $product->name;
                }
                return $productName;
            })
            ->addColumn('customer_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
                    $customerName = $customer->firstName.' '.$customer->lastName;
                }
                return $customerName;
            })
            ->editColumn('reportedDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $rep_date = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($rep_date){
                        $reportedDate = $rep_date->date_reported_to_alpha;
                    }else{
                        $reportedDate = "NA";
                    }
                }
                return $reportedDate;
            })
            ->addColumn('dateofLoss', function ($claim) {
                $dateofLoss = 'NA';
                if($claim->claim_number !=null){
                    $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($loss){
                        $dateofLoss =$loss->lossDate;
                    }
                }
                else{
                    $dateofLoss ='NA';
                }
                return $dateofLoss;
            })
            ->editColumn('ClaimType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claim_type = $claim->claim_type;
                }
                return $claim_type;
            })
            ->editColumn('riskAddress', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $riskAdd = CustomerProfile::where('customer_id',$claim->customer_id)->first();
                    if($riskAdd){
                        $riskAddress = $riskAdd->address;
                    }
                }
                return $riskAddress;
            })
            ->editColumn('paymentDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $paymentDate = '-';
                }
                return $paymentDate;
            })
            ->addColumn('transType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $trans = Transaction::where('customer_id',$claim->customer_id)->first();
                    if($trans){
                        $transType = $trans->transactionType;
                    }
                    else{
                        $transType = 'NA';
                    }
                }
                return $transType;
            })
            ->addColumn('transSubType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $trans = Transaction::where('customer_id',$claim->customer_id)->first();
                    if($trans){
                        $transType = Transactionsubtype::where('transaction_id',$trans->id)->first();
                        if($transType){
                            $transSubType = $transType->name;
                        }else{
                            $transSubType = 'NA';
                        }
                    }else{
                        $transSubType = 'NA';
                    }
                }
                return $transSubType;
            })
            ->editColumn('s_GroupName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $s_GroupName = '-';
                }
                return $s_GroupName;
            })
            ->editColumn('coveragName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $coveragName = '-';
                }
                return $coveragName;
            })
            ->editColumn('reservePaymentAmt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $reservePaymentAmt = '-';
                }
                return $reservePaymentAmt;
            })
            ->editColumn('motorDesc', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $motorDesc = '-';
                }
                return $motorDesc;
            })
            ->editColumn('netPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $netPercent = '-';
                }
                return $netPercent;
            })
            ->editColumn('quotaPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotaPercent = '-';
                }
                return $quotaPercent;
            })
            ->editColumn('quotaPSurplusPercentercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotaPSurplusPercentercent = '-';
                }
                return $quotaPSurplusPercentercent;
            })
            ->editColumn('facPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $facPercent = '-';
                }
                return $facPercent;
            })
            ->editColumn('Netretention_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Netretention_Amt = '-';
                }
                return $Netretention_Amt;
            })
            ->editColumn('Quotasharing_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Quotasharing_Amt = '-';
                }
                return $Quotasharing_Amt;
            })
            ->editColumn('Surplus_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Surplus_Amt = '-';
                }
                return $Surplus_Amt;
            })
            ->editColumn('Facultative_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Facultative_Amt = '-';
                }
                return $Facultative_Amt;
            })
            ->editColumn('s_TreatyName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $s_TreatyName = '-';
                }
                return $s_TreatyName;
            })
            ->editColumn('VATInclude', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $VATInclude = '-';
                }
                return $VATInclude;
            })
            ->editColumn('Regulatory_MappingName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Regulatory_MappingName = '-';
                }
                return $Regulatory_MappingName;
            })
            ->rawColumns(['status','total','endorse','product_id_count','name','dateofLoss'])
            ->make(true);
    }

    public function claimPaymentBordereauxExport(Request $request){
        try{
            return Excel::download(new ClaimPaymentBordereauxExport($request->all()), 'Claim Payment Bordereaux Export.xlsx');
        }catch(Exception $e){
        }
    }

    public function claimOutstandingAgeingReport(Request $request){
        return view('admin.reports.claimOutstandingAgeingReport');
    }
    public function claimOutstandingAgeingReportData(Request $request)
    {
        $claim = Claim::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->editColumn('claim_number', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimNumber = $claim->claim_number;
                }
                return $claimNumber;
            })
            ->addColumn('policyNumber', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('product_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $product = Product::where('id',$policy->product_id)->first();
                    $productName = $product->name;
                }
                return $productName;
            })
//            ->addColumn('customer_name', function ($claim) {
//                $policyNumber = 'NA';
//                if($claim->claim_number !=null){
//                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
//                    $customerName = $customer->firstName.' '.$customer->lastName;
//                }
//                return $customerName;
//            })
            ->editColumn('customer_name', function ($claim) {
                $cust_name = 'NA';
                if ($claim->claim_number != null)
                {
                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
                    if($customer != null) {
                        $cust_name = $customer->firstName.' '.$customer->lastName;
                    }
                    else {
                        $cust_name = 'N/A';
                    }
                }else{
                    $cust_name = 'N/A';
                }
                return $cust_name;
            })
            ->addColumn('dateofLoss', function ($claim) {
                $dateofLoss = 'NA';
                if($claim->claim_number !=null){
                    $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($loss){
                        $dateofLoss =$loss->lossDate;
                    }
                }
                else{
                    $dateofLoss ='NA';
                }
                return $dateofLoss;
            })
            ->editColumn('date_reported', function ($claim) {
                $date_reported = 'NA';
                if($claim->claim_number !=null){
                    $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($loss) {
                        $date_reported = $loss -> date_reported;
                    }
                }
                else{
                    $date_reported ='NA';
                }
                return $date_reported;
            })
            ->editColumn('status', function ($claim) {
                $status= 'NA';
                if($claim->claim_number !=null){
                    $status = $claim->status;
                }
                return $status;
            })
            ->editColumn('ClaimType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claim_type = '-';
                }
                return $claim_type;
            })

            ->editColumn('riskAddress', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $riskAddress = '-';
                }
                return $riskAddress;
            })
            ->editColumn('paymentDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $paymentDate = '-';
                }
                return $paymentDate;
            })
            ->addColumn('transType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $transType = '-';
                }
                return $transType;
            })
            ->editColumn('transSubType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $transSubType = '-';
                }
                return $transSubType;
            })


            ->rawColumns(['status','total','endorse','product_id_count','name','dateofLoss'])
            ->make(true);
    }
    public function claimOutstandingAgeingReportExport(Request $request){
        try{
            return Excel::download(new ClaimOutstandingAgeingReportExport($request->all()), 'Claim Outstanding Ageing Report Export.xlsx');
        }catch(Exception $e){
        }
    }

    public function reInsuranceRiskProfile(Request $request){
        return view('admin.reports.re_insurance_risk_profile');
    }

    public function reInsuranceRiskProfileData(Request $request)
    {
        $now = Carbon::now();
        $financialYearStart = Carbon::now();
        $financialYearStart->set('month', 7);
        $financialYearStart->set('day', 1);
        if($financialYearStart->greaterThan($now))
            $financialYearStart->subYear();

        $policyLedger = Ledger::join('policies', 'policies.id', '=', 'policy_ledger.policy_id')
            ->where('policy_ledger.trans_type','Invoice')
            ->select('policies.policyNumber', 'policies.billingStartDate','policies.sum_assured','policy_ledger.invoice_no','policy_ledger.accounting_date','policy_ledger.premium','policies.premium_freq','policies.created_at','policies.policyActivatedDate','policy_ledger.customer_id','policy_ledger.policy_id','policies.status','policy_ledger.id');
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $policyLedger->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }elseif ($request->filterDateFrom != '-1' && $request->filterDateto == '-1'){
            $policyLedger->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($policyLedger)
            ->addColumn('customer_name', function ($policyLedger) {
                $customer = Customer::where('id',$policyLedger->customer_id)->first(array('firstName','lastName'));
                if($customer != null) {
                    $cust_name = $customer->firstName.' '.$customer->lastName;
                }
                else {
                    $cust_name = 'N/A';
                }
                return $cust_name;
            })

            ->addColumn('term_start', function ($policyLedger) {
                $pos = strpos($policyLedger->accounting_date, '/');
                if ($pos !== false) {
                    $term_start = Carbon::createFromFormat('d/m/Y', $policyLedger->accounting_date)->format('d-m-Y');
                } else {
                    $term_start = Carbon::parse($policyLedger->accounting_date)->format('d-m-Y');
                }
                return $term_start;
            })
            ->addColumn('term_end', function ($policyLedger) {
                $pos = strpos($policyLedger->accounting_date, '/');
                if ($pos !== false) {
                    $policyLedger->accounting_date = Carbon::createFromFormat('d/m/Y', $policyLedger->accounting_date)->format('d-m-Y');
                }
                if( $policyLedger->premium_freq > 1)
                {
                    $term_end = Carbon::parse($policyLedger->accounting_date)->addYear()->subDay()->format('d-m-Y');

                } else {
                    $term_end = Carbon::parse($policyLedger->accounting_date)->addMonth()->subDay()->format('d-m-Y');
                }
                return $term_end;
            })
            ->addColumn('trans_type', function ($policyLedger) use ($financialYearStart) {
                $pos = strpos($policyLedger->created_at, '/');
                if ($pos !== false) {
                    $created_at = Carbon::createFromFormat('d/m/Y', $policyLedger->created_at);
                } else {
                    $created_at = Carbon::parse($policyLedger->created_at);
                    if($created_at->greaterThan($financialYearStart))
                        return "NEW BUSINESS";
                    else
                        return "RE ISSUE";
                }
            })
            ->addColumn('risk_name', function ($policyLedger) {
                $customer = CustomerProfile::where('customer_id',$policyLedger->customer_id)->first(array('address'));
                if($customer)
                {
                    $risk_name = $customer->address;
                }
                else
                {
                    $risk_name = 'NA';
                }

                return $risk_name;
            })

            ->addColumn('moter_decs', function ($policyLedger) {
                $vehicle = Vehicle::where('policy_id',$policyLedger->policy_id)->first(array('vehiclePlate'));
                if($vehicle)
                {
                    $moter_decs = $vehicle->vehiclePlate;
                }
                else
                {
                    $moter_decs = 'NA';
                }
                return $moter_decs;
            })

            ->addColumn('group_code', function ($policyLedger) {
                if($policyLedger->product_id == 2 || $policyLedger->product_id == 3)
                {
                    $group_code = 'MOTORDOMTP';
                }
                else
                {
                    $group_code = 'PERSONALACCIDENTDOM';
                }

                return $group_code;
            })
            ->addColumn('total_sum_insu', function ($policyLedger) {
                return $policyLedger->sum_assured;
            })
            ->addColumn('total_prem', function ($policyLedger) {
                return $policyLedger->premium;
            })
            ->addColumn('regu_mapping_name', function ($policyLedger) {
                $regu_mapping_name= 'Motor';
                return $regu_mapping_name;
            })
            ->addColumn('risk_band', function ($policyLedger) {
                $netretntion = $policyLedger->sum_assured;
                if($netretntion >= 0 && $netretntion <= 250000)
                {
                    $risk_band= '0 To 250000';
                }
                elseif($netretntion >= 250001 && $netretntion <= 500000)
                {
                    $risk_band= '250001 To 500000';
                }
                elseif($netretntion >= 500001 && $netretntion <= 1000000)
                {
                    $risk_band= '500001 To 1000000';
                }
                elseif($netretntion >= 1000001 && $netretntion <= 5000000)
                {
                    $risk_band= '1000001 To 5000000';
                }
                return $risk_band;
            })
            ->addColumn('max_tran', function ($policyLedger) {
                $max = Ledger::where('policy_id', $policyLedger->policy_id)->orderBy('id', 'desc')->first(array('status'));
                if($max->status  == 'Paid')
                    $max_tran= 'YES';
                else
                    $max_tran= 'NO';

                return $max_tran;
            })
            ->addColumn('booking_date', function ($policyLedger) {
                $booking_date= Carbon::parse($policyLedger->created_at)->format('d-m-Y');
                $policy = Policy::where('id',$policyLedger->policy_id)->first(array('created_at'));
                if($policy)
                {
                    $booking_date= Carbon::parse($policy->created_at)->format('d-m-Y');
                }
                return $booking_date;
            })
            ->rawColumns(['booking_date'])
            ->make(true);
    }


    public function reInsuranceRiskProfileExport(Request $request){
        try{
            return Excel::download(new ReInsuranceRiskProfileExport($request->all()), 'Re Insurance Risk Profile Export.xlsx');
        }catch(Exception $e){
        }
    }

    public function claimCoverageAllocationReport(Request $request){
        return view('admin.reports.claimCoverageAllocationReport');
    }

    public function getClaimCoverageAllocationReport(Request $request)
    {
        $claim = Claim::orderBy('created_at', 'ASC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->editColumn('claim_number', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimNumber = $claim->claim_number;
                }
                return $claimNumber;
            })
            ->addColumn('policyNumber', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('product_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $product = Product::where('id',$policy->product_id)->first();
                    $productName = $product->name;
                }
                return $productName;
            })
            ->addColumn('customer_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
                    $customerName = $customer->firstName.' '.$customer->lastName;
                }
                return $customerName;
            })
            ->addColumn('dateofLoss', function ($claim) {
                $dateofLoss = 'NA';
                if($claim->claim_number !=null){
                    $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($loss){
                        $dateofLoss =$loss->lossDate;
                    }
                }
                else{
                    $dateofLoss ='NA';
                }
                return $dateofLoss;
            })
            ->editColumn('reserved_date', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $reserved_date = '-';
                }
                return $reserved_date;
            })
            ->editColumn('reportedDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $rep_date = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($rep_date){
                        $reportedDate = $rep_date->date_reported_to_alpha;
                    }else{
                        $reportedDate = "NA";
                    }
                }
                return $reportedDate;
            })
            ->editColumn('ClaimType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claim_type = $claim->claim_type;
                }
                return $claim_type;
            })
            ->addColumn('transType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $trans = Transaction::where('customer_id',$claim->customer_id)->first();
                    if($trans){
                        $transType = $trans->transactionType;
                    }
                    else{
                        $transType = 'NA';
                    }
                }
                return $transType;
            })
            ->addColumn('transSubType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $trans = Transaction::where('customer_id',$claim->customer_id)->first();
                    if($trans){
                        $transType = Transactionsubtype::where('transaction_id',$trans->id)->first();
                        if($transType){
                            $transSubType = $transType->name;
                        }
                        else{
                            $transSubType = 'NA';
                        }
                    }

                    else{
                        $transSubType = 'NA';
                    }
                }
                return $transSubType;
            })
            ->editColumn('GroupName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $GroupName = '-';
                }
                return $GroupName;
            })
            ->editColumn('coveragName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $coveragName = '-';
                }
                return $coveragName;
            })
            ->editColumn('rservesPayment', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $rservesPayments = ClaimAccident::where('claim_id',$claim->id)->first();
                    if($rservesPayments){
                        $rservesPayment = $rservesPayments->reserve_amount;
                    }else{
                        $rservesPayment = "NA";
                    }

                }
                return $rservesPayment;
            })
            ->editColumn('motorDesc', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $motorDesc = '-';
                }
                return $motorDesc;
            })
            ->editColumn('netPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $netPercent = '-';
                }
                return $netPercent;
            })
            ->editColumn('quotaPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotaPercent = '-';
                }
                return $quotaPercent;
            })
            ->editColumn('surplusPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $surplusPercent = '-';
                }
                return $surplusPercent;
            })
            ->editColumn('facultative', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $facultative = '-';
                }
                return $facultative;
            })
            ->editColumn('Netretention_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Netretention_Amt = '-';
                }
                return $Netretention_Amt;
            })
            ->editColumn('quotasharing_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotasharing_Amt = '-';
                }
                return $quotasharing_Amt;
            })
            ->editColumn('Surplus_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Surplus_Amt = '-';
                }
                return $Surplus_Amt;
            })
            ->editColumn('Facultative_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Facultative_Amt = '-';
                }
                return $Facultative_Amt;
            })
            ->editColumn('PORiskMasterFK', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $PORiskMasterFK = '-';
                }
                return $PORiskMasterFK;
            })
            ->editColumn('ParentCoverageCode', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $ParentCoverageCode = '-';
                }
                return $ParentCoverageCode;
            })
            ->editColumn('s_TreatyName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $s_TreatyName = '-';
                }
                return $s_TreatyName;
            })
            ->rawColumns(['status','total','endorse','product_id_count','name','dateofLoss'])
            ->make(true);
    }

    public function claimCoverageAllocationExport(Request $request)
    {
        try {
            return Excel::download(new ClaimCoverageAllocationExport($request->all()), 'Claim Coverage Allocation Export.xlsx');
        } catch (Exception $e) {
        }
    }

    public function claimBordereauxOutstandingPayment(Request $request){
        return view('admin.reports.claimBordereauxOutstandingPayment');
    }

    public function getClaimBordereauxOutstandingPayment(Request $request)
    {
        $claim = Claim::orderBy('created_at', 'ASC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->editColumn('claim_number', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimNumber = $claim->claim_number;
                }
                return $claimNumber;
            })
            ->addColumn('policyNumber', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('product_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $product = Product::where('id',$policy->product_id)->first();
                    $productName = $product->name;
                }
                return $productName;
            })
            ->addColumn('customer_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
                    $customerName = $customer->firstName.' '.$customer->lastName;
                }
                return $customerName;
            })
            ->editColumn('reportedDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $rep_date = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($rep_date){
                        $reportedDate = $rep_date->date_reported_to_alpha;
                    }else{
                        $reportedDate = "NA";
                    }
                }
                return $reportedDate;
            })
            ->addColumn('dateofLoss', function ($claim) {
                $dateofLoss = 'NA';
                if($claim->claim_number !=null){
                    $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($loss){
                        $dateofLoss =$loss->lossDate;
                    }else{
                        $dateofLoss ='NA';
                    }
                    return $dateofLoss;
                }
                return $dateofLoss;
            })
            ->editColumn('riskAddress', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $riskAdd = CustomerProfile::where('customer_id',$claim->customer_id)->first();
                    if($riskAdd){
                        $riskAddress = $riskAdd->address;
                    }
                }
                return $riskAddress;
            })
            ->editColumn('GroupName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $GroupName = '-';
                }
                return $GroupName;
            })
            ->editColumn('motorDesc', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $motorDesc = '-';
                }
                return $motorDesc;
            })
            ->editColumn('totalOutstandingAmt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $totalOutstandingAmt = '-';
                }
                return $totalOutstandingAmt;
            })
            ->editColumn('netPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $netPercent = '-';
                }
                return $netPercent;
            })
            ->editColumn('quotaPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotaPercent = '-';
                }
                return $quotaPercent;
            })
            ->editColumn('surplusPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $surplusPercent = '-';
                }
                return $surplusPercent;
            })
            ->editColumn('facPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $facPercent = '-';
                }
                return $facPercent;
            })
            ->editColumn('Netretention_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Netretention_Amt = '-';
                }
                return $Netretention_Amt;
            })
            ->editColumn('quotasharing_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotasharing_Amt = '-';
                }
                return $quotasharing_Amt;
            })
            ->editColumn('Surplus_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Surplus_Amt = '-';
                }
                return $Surplus_Amt;
            })
            ->editColumn('Facultative_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Facultative_Amt = '-';
                }
                return $Facultative_Amt;
            })
            ->editColumn('s_TreatyName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $s_TreatyName = '-';
                }
                return $s_TreatyName;
            })
            ->editColumn('claimSubType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimSubTypes = ClaimAccident::where('claim_id',$claim->id)->first();
                    if($claimSubTypes){
                        $claimSubType = $claimSubTypes->claim_sub_type;
                    }else{
                        $claimSubType = "NA";
                    }
                }
                return $claimSubType;
            })
            ->editColumn('regulatoryMappingName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $regulatoryMappingName = '-';
                }
                return $regulatoryMappingName;
            })
            ->rawColumns(['status','total','endorse','product_id_count','name','dateofLoss'])
            ->make(true);
    }

    public function claimBordereauxOutstandingPaymentExport(Request $request)
    {
        try {
            return Excel::download(new ClaimBordereauxOutstandingPaymentExport($request->all()), 'Claim Bordereaux Outstanding Payment Export Report.xlsx');
        } catch (Exception $e) {
        }
    }

    public function claimBordereaux(Request $request){
        return view('admin.reports.claimBordereaux');
    }

    public function getClaimBordereaux(Request $request)
    {
        $claim = Claim::orderBy('created_at', 'ASC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->editColumn('claim_number', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimNumber = $claim->claim_number;
                }
                return $claimNumber;
            })
            ->addColumn('policyNumber', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->addColumn('product_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $product = Product::where('id',$policy->product_id)->first();
                    $productName = $product->name;
                }
                return $productName;
            })
            ->addColumn('customer_name', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
                    $customerName = $customer->firstName.' '.$customer->lastName;
                }
                return $customerName;
            })
            ->editColumn('reportedDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $rep_date = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($rep_date){
                        $reportedDate = $rep_date->date_reported_to_alpha;
                    }else{
                        $reportedDate = "NA";
                    }
                }
                return $reportedDate;
            })
            ->addColumn('dateofLoss', function ($claim) {
                $dateofLoss = 'NA';
                if($claim->claim_number !=null){
                    $loss = ClaimCellphone::where('claim_id',$claim->id)->first();
                    if($loss){
                        $dateofLoss =$loss->lossDate;
                    }else{
                        $dateofLoss ='NA';
                    }
                    return $dateofLoss;
                }
                return $dateofLoss;
            })
            ->editColumn('ClaimType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claim_type = $claim->claim_type;
                }
                return $claim_type;
            })
            ->editColumn('riskAddress', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $riskAdd = CustomerProfile::where('customer_id',$claim->customer_id)->first();
                    if($riskAdd){
                        $riskAddress = $riskAdd->address;
                    }
                }
                return $riskAddress;
            })
            ->editColumn('paymentDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $paymentDate = '-';
                }
                return $paymentDate;
            })
            ->addColumn('transType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $trans = Transaction::where('customer_id',$claim->customer_id)->first();
                    if($trans){
                        $transType = $trans->transactionType;
                    }
                    else{
                        $transType = 'NA';
                    }
                }
                return $transType;
            })
            ->addColumn('transSubType', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $trans = Transaction::where('customer_id',$claim->customer_id)->first();
                    if($trans){
                        $transType = Transactionsubtype::where('transaction_id',$trans->id)->first();
                        if($transType){
                            $transSubType = $transType->name;
                        }else{
                            $transSubType = 'NA';
                        }
                    }else{
                        $transSubType = 'NA';
                    }
                }
                return $transSubType;
            })
            ->editColumn('reservePaymentAmt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $rservesPayments = ClaimAccident::where('claim_id',$claim->id)->first();
                    if($rservesPayments){
                        $rservesPayment = $rservesPayments->reserve_amount;
                    }else{
                        $rservesPayment = "NA";
                    }
                }
                return $rservesPayment;
            })
            ->editColumn('GroupName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $GroupName = '-';
                }
                return $GroupName;
            })
            ->editColumn('coveragName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $coveragName = '-';
                }
                return $coveragName;
            })
            ->editColumn('motorDesc', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $motorDesc = '-';
                }
                return $motorDesc;
            })
            ->editColumn('motorDesc2', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $motorDesc2 = '-';
                }
                return $motorDesc2;
            })
            ->editColumn('netPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $netPercent = '-';
                }
                return $netPercent;
            })
            ->editColumn('quotaPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotaPercent = '-';
                }
                return $quotaPercent;
            })
            ->editColumn('surplusPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $surplusPercent = '-';
                }
                return $surplusPercent;
            })
            ->editColumn('facPercent', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $facPercent = '-';
                }
                return $facPercent;
            })
            ->editColumn('Netretention_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Netretention_Amt = '-';
                }
                return $Netretention_Amt;
            })
            ->editColumn('Quotasharing_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $quotasharing_Amt = '-';
                }
                return $quotasharing_Amt;
            })
            ->editColumn('Surplus_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Surplus_Amt = '-';
                }
                return $Surplus_Amt;
            })
            ->editColumn('Facultative_Amt', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Facultative_Amt = '-';
                }
                return $Facultative_Amt;
            })
            ->editColumn('Netretention_SI', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Netretention_SI = '-';
                }
                return $Netretention_SI;
            })
            ->editColumn('Quotasharing_SI', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Quotasharing_SI = '-';
                }
                return $Quotasharing_SI;
            })
            ->editColumn('Surplus_SI', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Surplus_SI = '-';
                }
                return $Surplus_SI;
            })

            ->editColumn('Facultative_SI', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Facultative_SI = '-';
                }
                return $Facultative_SI;
            })
            ->editColumn('Tran_PK', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $Tran_PK = '-';
                }
                return $Tran_PK;
            })
            ->editColumn('RiskMasterFK', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $RiskMasterFK = '-';
                }
                return $RiskMasterFK;
            })
            ->editColumn('MotorPK', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $MotorPK = '-';
                }
                return $MotorPK;
            })
            ->editColumn('ParentCoverageCode', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $ParentCoverageCode = '-';
                }
                return $ParentCoverageCode;
            })
            ->editColumn('s_TreatyName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $s_TreatyName = '-';
                }
                return $s_TreatyName;
            })
            ->editColumn('RegulatoryMappingName', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $RegulatoryMappingName = '-';
                }
                return $RegulatoryMappingName;
            })
            ->editColumn('TermStartDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $TermStartDate = '-';
                }
                return $TermStartDate;
            })
            ->editColumn('TermEndDate', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $TermEndDate = '-';
                }
                return $TermEndDate;
            })
            ->editColumn('VATInclude', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $VATInclude = '-';
                }
                return $VATInclude;
            })
            ->rawColumns(['status','total','endorse','product_id_count','name','dateofLoss'])
            ->make(true);
    }

    public function claimBordereauxExport(Request $request)
    {
        try {
            return Excel::download(new ClaimBordereauxExport($request->all()), 'Claim Bordereaux Export Report.xlsx');
        } catch (Exception $e) {
        }
    }

    public function claimPaymentAsOnDate(Request $request){
        return view('admin.reports.claim_payment_as_on_date');
    }

    public function claimPaymentAsOnDateData(Request $request)
    {
        $claim = Claim::orderBy('created_at', 'DESC');
        if (request('filterDateFrom') != '-1' && request('filterDateto') != '-1')
        {
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse(request('filterDateto'))
                ->format('Y-m-d') ]);
        }elseif (request('filterDateFrom') != '-1' && request('filterDateto') == '-1'){
            //Carbon::parse('today')
            $claim->whereBetween(DB::raw('date(created_at)') , [Carbon::parse(request('filterDateFrom'))
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        return DataTables::eloquent($claim)
            ->editColumn('claim_number', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claimNumber = $claim->claim_number;
                }
                return $claimNumber;
            })
            ->addColumn('policyNumber', function ($claim) {
                $policyNumber = 'NA';
                if($claim->claim_number !=null){
                    $policy = Policy::where('id',$claim->policy_id)->first();
                    $policyNumber = $policy->policyNumber;
                }
                return $policyNumber;
            })
            ->editColumn('check_no', function ($claim) {
                $check_no = 'NA';
                if($claim->claim_number !=null){
                    $check_no = '-';
                }
                return $check_no;
            })
            ->addColumn('date', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $date = $policy->date;
                    }
                    else
                    {
                        $date = 'NA';
                    }

                }
                else
                {
                    $date = 'NA';
                }
                return $date;
            })
            ->editColumn('customer_name', function ($claim) {
                $cust_name = 'NA';
                if ($claim->claim_number != null)
                {
                    $customer = Customer::where('id',$claim->customer_id)->first(array('firstName','lastName'));
                    if($customer != null) {
                        $cust_name = $customer->firstName.' '.$customer->lastName;
                    }
                    else {
                        $cust_name = 'N/A';
                    }
                }else{
                    $cust_name = 'N/A';
                }
                return $cust_name;
            })
            ->addColumn('payee', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $payee = $policy->payee;
                    }
                    else
                    {
                        $payee = 'NA';
                    }

                }
                else
                {
                    $payee = 'NA';
                }
                return $payee;
            })
            ->addColumn('reserve_amt', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReservesCoverage::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $reserve_amt = $policy->reserve_amt;
                    }
                    else
                    {
                        $reserve_amt = 'NA';
                    }

                }
                else
                {
                    $reserve_amt = 'NA';
                }
                return $reserve_amt;
            })
            ->addColumn('transaction_type', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $transaction_type = $policy->transaction_type;
                    }
                    else
                    {
                        $transaction_type = 'NA';
                    }

                }
                else
                {
                    $transaction_type = 'NA';
                }
                return $transaction_type;
            })
            ->addColumn('transaction_sub_type', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $transaction_sub_type = $policy->transaction_type;
                    }else
                    {
                        $transaction_sub_type = 'NA';
                    }
                }else
                {
                    $transaction_sub_type = 'NA';
                }
                return $transaction_sub_type;
            })
            ->editColumn('claim_type', function ($claim) {
                $claimNumber = 'NA';
                if($claim->claim_number !=null){
                    $claim_type = $claim->claim_type;
                }
                return $claim_type;
            })
            ->editColumn('inserted_date', function ($claim) {
                $check_no = 'NA';
                if($claim->claim_number !=null){
                    $inserted_date = '-';
                }
                return $inserted_date;
            })
            ->addColumn('include_vat', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $include_vat = $policy->include_vat;
                    }
                    else
                    {
                        $include_vat = 'NA';
                    }

                }
                else
                {
                    $include_vat = 'NA';
                }
                return $include_vat;
            })
            ->addColumn('invoice_no', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $invoice_no = $policy->invoice_no;
                    }
                    else
                    {
                        $invoice_no = 'NA';
                    }

                }
                else
                {
                    $invoice_no= 'NA';
                }
                return $invoice_no;
            })
            ->addColumn('invoice_date', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $invoice_date = $policy->invoice_date;
                    }
                    else
                    {
                        $invoice_date = 'NA';
                    }

                }
                else
                {
                    $invoice_date= 'NA';
                }
                return $invoice_date;
            })
            ->addColumn('invoice_due_date', function ($claim) {
                $date = 'NA';
                if($claim->claim_number !=null){
                    $policy = ClaimReserves::where('claim_id',$claim->id)->first();
                    if($policy)
                    {
                        $invoice_due_date = $policy->invoice_due_date;
                    }
                    else
                    {
                        $invoice_due_date= 'NA';
                    }

                }
                else
                {
                    $invoice_due_date = 'NA';
                }
                return $invoice_due_date;
            })
            ->editColumn('printed', function ($claim) {
                $check_no = 'NA';
                if($claim->claim_number !=null){
                    $printed = '-';
                }
                return $printed;
            })
            ->editColumn('printed_date', function ($claim) {
                $check_no = 'NA';
                if($claim->claim_number !=null){
                    $printed_date = '-';
                }
                return $printed_date;
            })

            ->rawColumns(['status','total','endorse','product_id_count','name','dateofLoss'])
            ->make(true);
    }

    public function claimPaymentAsOnDateDataExport(Request $request){
        try{
            return Excel::download(new ClaimPaymentAsOnDateDataExport($request->all()), 'Claim Payment As On Date Data Export.xlsx');
        }catch(Exception $e){
        }
    }

    public function reinsuranceRiskSummaryPDF()
    {
        $life = array();
        $life['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->where('product_id', 1)
            ->count();

        $life['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->where('product_id', 1)
            ->sum('premium');

        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->where('claim_type', 'Life')
            ->get(array('id'));

        if($claims == NULL)
        {
            $life['claim_count'] = 0;
            $life['payment'] = 0;
            $life['reserve'] = 0;
        } else {
            $life['claim_count'] = $claims->count();
            $life['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $life['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        $vehicle = array();
        $vehicle[0]['risk_band'] = '0 to 250 000';
        $vehicle[0]['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('product_id', [2])
            ->where('sum_assured', '<=', '250000')
            ->count();

        $vehicle[0]['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('product_id', [2])
            ->where('sum_assured', '<=', '250000')
            ->sum('premium');

        $vehicle[0]['sum_assured'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('product_id', [2])
            ->where('sum_assured', '<=', '250000')
            ->sum('sum_assured');

        $policy = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('product_id', [2])
            ->where('sum_assured', '<=', '250000')
            ->get(array('id'));

        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('policy_id', $policy->pluck('id'))
            ->get(array('id'));

        if($claims == NULL)
        {
            $vehicle[0]['claim_count'] = 0;
            $vehicle[0]['payment'] = 0;
            $vehicle[0]['reserve'] = 0;
        } else {
            $vehicle[0]['claim_count'] = $claims->count();
            $vehicle[0]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[0]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $vehicle[1]['risk_band'] = '250 001 - 500 000';
        $vehicle[1]['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('product_id', [2])
            ->where('sum_assured', '>', '250000')
            ->where('sum_assured', '<=', '500000')->count();
        $vehicle[1]['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])
            ->whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->sum('premium');
        $vehicle[1]['sum_assured'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->sum('sum_assured');

        $policy = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [2])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->get(array('id'));
        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[1]['claim_count'] = 0;
            $vehicle[1]['payment'] = 0;
            $vehicle[1]['reserve'] = 0;
        } else {
            $vehicle[1]['claim_count'] = $claims->count();
            $vehicle[1]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[1]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $vehicle[2]['risk_band'] = '500 000 +';
        $vehicle[2]['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [2])->where('sum_assured', '>', '500000')->count();
        $vehicle[2]['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [2])->where('sum_assured', '>', '500000')->sum('premium');
        $vehicle[2]['sum_assured'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [2])->where('sum_assured', '>', '500000')->sum('sum_assured');
        $policy = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [2])->where('sum_assured', '>', '500000')->get(array('id'));
        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $vehicle[2]['claim_count'] = 0;
            $vehicle[2]['payment'] = 0;
            $vehicle[2]['reserve'] = 0;
        } else {
            $vehicle[2]['claim_count'] = $claims->count();
            $vehicle[2]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $vehicle[2]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }

        $motorComp = array();
        $motorComp[0]['risk_band'] = '0 to 250 000';
        $motorComp[0]['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->count();
        $motorComp[0]['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->sum('premium');
        $motorComp[0]['sum_assured'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->sum('sum_assured');
        $policy = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '<=', '250000')->get(array('id'));
        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[0]['claim_count'] = 0;
            $motorComp[0]['payment'] = 0;
            $motorComp[0]['reserve'] = 0;
        } else {
            $motorComp[0]['claim_count'] = $claims->count();
            $motorComp[0]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[0]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $motorComp[1]['risk_band'] = '250 001 - 500 000';
        $motorComp[1]['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->count();
        $motorComp[1]['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->sum('premium');
        $motorComp[1]['sum_assured'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->sum('sum_assured');
        $policy = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '250000')->where('sum_assured', '<=', '500000')->get(array('id'));
        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[1]['claim_count'] = 0;
            $motorComp[1]['payment'] = 0;
            $motorComp[1]['reserve'] = 0;
        } else {
            $motorComp[1]['claim_count'] = $claims->count();
            $motorComp[1]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[1]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $motorComp[2]['risk_band'] = '500 000 +';
        $motorComp[2]['policy_count'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '500000')->count();
        $motorComp[2]['premium'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '500000')->sum('premium');
        $motorComp[2]['sum_assured'] = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '500000')->sum('sum_assured');
        $policy = Policy::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('product_id', [3])->where('sum_assured', '>', '500000')->get(array('id'));
        $claims = Claim::whereBetween(\Illuminate\Support\Facades\DB::raw('date(created_at)') , [Carbon::parse('2020-07-01')
            ->format('Y-m-d')  , Carbon::parse('2021-03-31')
            ->format('Y-m-d') ])->whereIn('policy_id', $policy->pluck('id'))->get(array('id'));
        if($claims == NULL)
        {
            $motorComp[2]['claim_count'] = 0;
            $motorComp[2]['payment'] = 0;
            $motorComp[2]['reserve'] = 0;
        } else {
            $motorComp[2]['claim_count'] = $claims->count();
            $motorComp[2]['payment'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('payment_amt');
            $motorComp[2]['reserve'] = ClaimReservesCoverage::whereIn('claim_id', $claims->pluck('id'))->sum('reserve_amt');
        }
        $data = [
            'life'=>$life,
            'vehicle'=>$vehicle,
            'motorComp'=>$motorComp
        ];
        $t = \Carbon\Carbon::now()->timestamp;
        $path = 'ReinsuranceRiskSummary/'.$t.'/Reinsurance_Summary.pdf';
        $pdf = PDF::loadView('admin.notes.reinsurancePDF', $data);
        Storage::disk('s3')->put($path, $pdf->output(), 'public');
        return Storage::disk('s3')->download($path);
    }

    public function getOrangeTransactions(){
        return view('admin.reports.orange_transactions');
    }

    public function getOrangeTransactionReport(){
        return view('admin.reports.orange_report');
    }

    public function orangeReportData(Request $request){
        $query = PaymentSchedule::where('policy_number','!=',null);

        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(payment_date)') , [Carbon::parse($request->filterDateFrom)
                ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                ->format('Y-m-d') ]);
        }else{
            $query->where('is_approaching',1);
        }

        $payment = $query->get();

        return \Yajra\DataTables\DataTables::of($payment)
            ->editColumn('payment_method',function ($data){
                if($data->payment_method == 'orangeMoney')
                    return 'Orange USSD';
                else
                    return '-';
            })
            ->editColumn('customer_id',function ($data){
                $customerData = Customer::leftJoin('customer_profile','customer_profile.customer_id','customer.id')
                    ->where('customer.id',$data->customer_id)
                    ->first();
                if($customerData != null){
                    return $customerData->firstName.' '.$customerData->middleName.' '.$customerData->lastName;
                }
                else{
                    return 'N/A';
                }
            })
            ->editColumn('cellphone',function ($data){
                $profile = Customer::where('id',$data->customer_id)->first(array('cellphone'));
                if($profile && $profile->cellphone)
                    return $profile->cellphone;
                else
                    return 'N/A';
            })
            ->editColumn('status',function ($data){
                if($data->status == 0)
                    return 'Pending';
                elseif ($data->status == 1)
                    return 'Success-'.$data->reference_number;
                elseif($data->status == 2)
                    return 'Failed';
                else
                    return 'N/A';


            })
            ->editColumn('product',function ($data){

                $product = Policy::leftJoin('policy_payment_schedules','policy_payment_schedules.policy_number','policies.policyNumber')
                    ->leftJoin('products','products.id','policies.product_id')
                    ->where('policies.policyNumber',$data->policy_number)
                    ->first(array('products.slug'));

                if($product && $product->slug)
                    return $product->slug;
                else
                    return 'N/A';
            })
            ->editColumn('plan',function ($data){
                $plan = Policy::leftJoin('policy_payment_schedules','policy_payment_schedules.policy_number','policies.policyNumber')
                    ->leftJoin('product_plans','product_plans.id','policies.plan_id')
                    ->where('policies.policyNumber',$data->policy_number)
                    ->first(array('product_plans.slug'));

                if($plan && $plan->slug)
                    return $plan->slug;
                else
                    return 'N/A';
            })
            ->rawColumns(['paymentMethod','customer_id','cellphone','product','plan','status'])
            ->make(true);
    }

    public function getOrangeTransactionsData(){
        $data = PaymentTransaction::where('paymentMethod','orangeMoney')->orderBy('id','DESC')->get();
        return \Yajra\DataTables\DataTables::of($data)
            ->editColumn('paymentMethod',function ($data){
                if($data->paymentMethod == 'orangeMoney')
                    return 'Orange USSD';
                else
                    return '-';
            })
            ->editColumn('paymentLoggedBy',function ($data){
                if($data->paymentLoggedBy != null){
                    $user = User::where('id',$data->paymentLoggedBy)->first(array('firstName','lastName'));
                    if($user){
                        return $name = ucfirst($user->firstName.' '.$user->lastName);
                    }else{
                        return $name = '-';
                    }
                }else{
                    return $name = '-';
                }
            })
            ->rawColumns(['paymentMethod','paymentLoggedBy'])
            ->make(true);
    }

    public function orangeReport(){
        return view('admin.reports.orange_report');
    }

}
