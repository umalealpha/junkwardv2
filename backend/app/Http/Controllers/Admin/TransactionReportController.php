<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Activation;
use AlphaDirect\Branch;
use AlphaDirect\Customer;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Stores;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\VcsNewTransaction;
use AlphaDirect\VcsTransaction;
use Illuminate\Support\Facades\Auth;
use Redirect;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use AlphaDirect\PaymentTransaction;

class TransactionReportController extends Controller
{

    public function index()
    {
        if (Auth::user()->hasPermissionTo('transaction-report-list')) {
            $policy = '';
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
            $policyAgents = Policy::join('users', 'users.id', 'policies.agent_id')
                ->where('users.active', 1)
                ->where('policies.agent_id', '!=', 'null')
                ->groupBy('policies.agent_id')
                ->get();

            //  return response($policyLeadAgent);
            return view('admin.reports.transactionReport', compact('policy', 'products', 'productPlans', 'customerNumbers', 'policyAgents'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }
    public function NGPaymentReport()
    {
        if (Auth::user()->hasPermissionTo('transaction-report-list')) {
            $policy = '';
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
            $policyAgents = Policy::join('users', 'users.id', 'policies.agent_id')
                ->where('users.active', 1)
                ->where('policies.agent_id', '!=', 'null')
                ->groupBy('policies.agent_id')
                ->get();

            //  return response($policyLeadAgent);
            return view('admin.reports.ngtransactionReport', compact('policy', 'products', 'productPlans', 'customerNumbers', 'policyAgents'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    public function realpayTransaction(){
        if (Auth::user()->hasPermissionTo('transaction-report-list')) {
            return view('admin.reports.realpay_transactions');
        }
        else
            {
                return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
            }
    }

    public function alltransactionData(Request $request)
    {
        /*
            current vcsnewTransaction
            realpay transactions
        */
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
        $searchValue = $search_arr['value']; // Search value

        // Total records
        $totalRecords = VcsNewTransaction::select('count(*) as allcount')->count();

        $query = VcsNewTransaction::join('policies','policies.policyNumber','vcs_new_transactions.policyNumber')
            ->join('customer','customer.id','policies.customer_id')
            ->join('products','products.id','policies.product_id')
            ->join('product_plans','product_plans.id','policies.plan_id')
            ->orderBy('vcs_new_transactions.policyNumber', 'DESC');

        if($searchValue != null){
            $query->where('policies.policyNumber', 'like', '%' .$searchValue . '%')
                ->orWhere('policies.product_id', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' .$searchValue . '%')
                ->orWhere('product_plans.name', 'like', '%' .$searchValue . '%')
                ->orWhere('products.name', 'like', '%' .$searchValue . '%');
        }

        if ($request->product_plan != - 1)
        {
            $query->where('policies.plan_id', $request->product_plan);
        }
        if ($request->product_filter != - 1)
        {
            $query->where('policies.product_id', $request->product_filter);
        }
        if ($request->policyStatus_filter != - 1)
        {
            $query->where('policies.status', $request->policyStatus_filter);
        }
        if ($request->policy_number != - 1)
        {
            $query->where('vcs_new_transactions.policyNumber','like', '%' .  $request->policy_number . '%');
        }
        if ($request->reference_number != - 1)
        {
            $query->where('vcs_new_transactions.reference','like', '%' .  $request->reference_number . '%');
        }
        if ($request->contact_number != - 1)
        {

            $customerId = Customer::where('cellphone', 'like', '%' . $request->contact_number . '%')
                ->first('id');

            $query->where('policies.customer_id', $customerId->id);

        }
        if ($request->premium_value != - 1)
        {
            $query->where('vcs_new_transactions.amount','like', '%' . $request->premium_value . '%');
        }
        if ($request->customer_name != - 1)
        {
            $customer = Customer::where('firstName', 'like', '%' . $request->customer_name . '%')
                ->orWhere('lastName', 'like', '%' . $request->customer_name . '%')
                ->pluck('id');
            $query->whereIn('policies.customer_id', $customer);
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(vcs_new_transactions.created_at)') ,
                [
                    Carbon::parse($request->filterDateFrom)->format('Y-m-d') ,
                    Carbon::parse($request->filterDateto)->format('Y-m-d')
                ]
            );
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto == '-1')
        {
            $query->whereBetween(DB::raw('date(vcs_new_transactions.created_at)') ,
                [
                    Carbon::parse($request->filterDateFrom)->format('Y-m-d') ,
                    Carbon::parse('today')->format('Y-m-d')
                ]
            );
        }
        if ($request->filterPolicyDateFrom != '-1' && $request->filterPolicyDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(policies.policyActivatedDate)') ,
                [
                    Carbon::parse($request->filterPolicyDateFrom)->format('Y-m-d') ,
                    Carbon::parse($request->filterPolicyDateto)->format('Y-m-d')
                ]
            );
        }
        if ($request->filterPolicyDateFrom != '-1' && $request->filterPolicyDateto == '-1')
        {
            $query->whereBetween(DB::raw('date(policies.policyActivatedDate)') ,
                [
                    Carbon::parse($request->filterPolicyDateFrom)->format('Y-m-d') ,
                    Carbon::parse('today')->format('Y-m-d')
                ]
            );
        }
        if ($request->agent_id != '-1')
        {
            $query->where('policies.agent_id', $request->agent_id);
        }
        if ($request->trans_status != '-1')
        {
            $query->where('vcs_new_transactions.status', 'like', '%' .  $request->trans_status . '%');
        }
        if ($request->transType != '-1')
        {
            $query->where('vcs_new_transactions.transType',$request->transType);
        }

        if($searchValue != null){
            $totalRecordswithFilter = VcsNewTransaction::join('policies','policies.policyNumber','vcs_new_transactions.policyNumber')
                ->join('customer','customer.id','policies.customer_id')
                ->join('products','products.id','policies.product_id')
                ->join('product_plans','product_plans.id','policies.plan_id')
                ->where('policies.policyNumber', 'like', '%' .$searchValue . '%')
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
                    'vcs_new_transactions.id',
                    'policies.id as policy_id',
                    'policies.customer_id',
                    'policies.plan_id',
                    'policies.premium',
                    'vcs_new_transactions.amount',
                    'vcs_new_transactions.reference',
                    'policies.product_id',
                    'policies.storeID',
                    'policies.billing_day',
                    'policies.status as policy_status',
                    'policies.billingStartDate',
                    'policies.first_premium',
                    'policies.premium_freq',
                    'policies.premium',
                    'policies.activation_code',
                    'vcs_new_transactions.policyNumber as policyNumber',
                    'policies.agent_id',
                    'policies.status as policyStatus',
                    'vcs_new_transactions.created_at as created_at',
                    'vcs_new_transactions.terminal_id',
                    'vcs_new_transactions.authorision_Date',
                    'vcs_new_transactions.status as paymentStatus',
                    'vcs_new_transactions.statusRef',
                    'vcs_new_transactions.transType',
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

            if($record['product_id']){
                $product = Product::where('id',$record['product_id'])->first();
                if($product && $product->name)
                    $productname = $product->name;
                else
                    $productname = 'N/A';
            }else{
                $productname = 'N/A';
            }

            if ($record['premium'] != null)
            {
                $amount =  number_format((float)$record['premium'], 2, '.', '');
            }else{
                $amount = number_format((float)0, 2, '.', '');;
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

            if($record['policy_status'] == 1){
                $p_status = 'Activated';
            }elseif($record['policy_status'] == 0){
                $p_status = 'Deactivated';
            }elseif($record['policy_status'] == 2){
                $p_status = 'Cancelled';
            }else{
                $p_status = 'Not found';
            }

            if($record['first_premium'] != null && $record['premium_freq'] == 1) {
                if($record['premium'] != $record['first_premium'])
                    $fp = $record['first_premium'];
                else
                    $fp = '-';
            }
            else {
                $fp = '-';
            }

            if($record['policyNumber'])
                $policyNumber = $record['policyNumber'];
            else
                $policyNumber = 'N/A';

            if($record['storeID'] != null) {
                $store = Stores::where('id',$record['storeID'])->first();
                $storeName = $store->name;
            }
            else {
                $storeName = '-';
            }

            if($record['reference'])
                $referenceNumber = $record['reference'];
            else
                $referenceNumber = 'N/A';

            if($record['authorision_Date'] != null){
                $authorisionTime = date('h:i A', strtotime($record['authorision_Date']));
            }else{
                $authorisionTime = 'N/A';
            }

            if ($record['premium_freq'] != null){
                if($record['premium_freq'] == 1){
                    $freq = 'Monthly';
                }
                elseif($record['premium_freq'] == 2){
                    $freq = 'Three Instalments';
                }
                elseif($record['premium_freq'] == 3){
                    $freq = 'Annual';
                }
                else{
                    $freq = 'Frequency not described';
                }


            }elseif($record['premium_freq'] == null && ($record['product_id'] != null && $record['product_id'] != 3))
            {
                $freq = 'Monthly';
            }
            else{
                $freq = 'Frequency not found';
            }

            if($record['billingStartDate'] != null)
                $b_date = $record['billingStartDate'];
            else
                $b_date =  '-';

            if($record['billing_day'] != null)
                $b_day = $record['billing_day'];
            else
                $b_day =  '-';

            if($record['authorision_Date'] != null){
                $timestamp = $record['authorision_Date'];
                $datetime = explode(" ",$timestamp);
                $date = $datetime[0];

                try{
                    if(Carbon::createFromFormat("Y-m-d", $date)){
                        $date = Carbon::createFromFormat("Y-m-d", $date)->timestamp;
                        $newDate = date("d/m/Y", $date);
                        $auth_date = $newDate;
                    }
                    else{
                        $auth_date = $date;
                    }
                }catch(\Exception $e){
                    $auth_date = $date;
                }
            }else{
                $auth_date = $datetime;
            }

            $data_arr[] = array(
                "id" => $id,
                "policyNumber" => $policyNumber,
                "product_name" => $productname,
                "plan_id" => $planname,
                "first_premium" => $fp,
                "amount" => $amount,
                "premium_freq" => $freq,
                "billing_day" => $b_day,
                "billing_date" => $b_date,
                "store" => $storeName,
                "agent_id" => $agentName,
                "transType" => $record['transType'],
                "paymentStatus" => $record['paymentStatus'],
                "statusRef" =>$record['statusRef'],
                "reference" =>$referenceNumber,
                "transaction_Date" =>$auth_date,
                "policy_status" =>$p_status,
                "transaction_Time" =>$authorisionTime

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
    public function ngdata(Request $request)
    {
        /*
            current vcsnewTransaction
            realpay transactions
        */
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
        $searchValue = $search_arr['value']; // Search value

        // Total records
        $totalRecords = PaymentTransaction::where('paymentMethod','N-Genius')->select('count(*) as allcount')->count();

        $query = PaymentTransaction::join('policies','policies.policyNumber','payment_transactions.policyNumber')
            ->join('customer','customer.id','policies.customer_id')
            ->join('products','products.id','policies.product_id')
            ->join('product_plans','product_plans.id','policies.plan_id')
            ->where('payment_transactions.paymentMethod','N-Genius')
            ->orderBy('payment_transactions.id', 'DESC');

        if($searchValue != null){
            $query->where('policies.policyNumber', 'like', '%' .$searchValue . '%')
                ->orWhere('policies.product_id', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.firstName', 'like', '%' .$searchValue . '%')
                ->orWhere('customer.lastName', 'like', '%' .$searchValue . '%')
                ->orWhere('product_plans.name', 'like', '%' .$searchValue . '%')
                ->orWhere('products.name', 'like', '%' .$searchValue . '%');
        }

        if ($request->product_plan != - 1)
        {
            $query->where('policies.plan_id', $request->product_plan);
        }
        if ($request->product_filter != - 1)
        {
            $query->where('policies.product_id', $request->product_filter);
        }
        if ($request->policyStatus_filter != - 1)
        {
            $query->where('policies.status', $request->policyStatus_filter);
        }
        if ($request->policy_number != - 1)
        {
            $query->where('payment_transactions.policyNumber','like', '%' .  $request->policy_number . '%');
        }
        if ($request->reference_number != - 1)
        {
            $query->where('payment_transactions.reference','like', '%' .  $request->reference_number . '%');
        }
        if ($request->contact_number != - 1)
        {

            $customerId = Customer::where('cellphone', 'like', '%' . $request->contact_number . '%')
                ->first('id');

            $query->where('policies.customer_id', $customerId->id);

        }
        if ($request->premium_value != - 1)
        {
            $query->where('payment_transactions.amount','like', '%' . $request->premium_value . '%');
        }
        if ($request->customer_name != - 1)
        {
            $customer = Customer::where('firstName', 'like', '%' . $request->customer_name . '%')
                ->orWhere('lastName', 'like', '%' . $request->customer_name . '%')
                ->pluck('id');
            $query->whereIn('policies.customer_id', $customer);
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(payment_transactions.created_at)') ,
                [
                    Carbon::parse($request->filterDateFrom)->format('Y-m-d') ,
                    Carbon::parse($request->filterDateto)->format('Y-m-d')
                ]
            );
        }
        if ($request->filterDateFrom != '-1' && $request->filterDateto == '-1')
        {
            $query->whereBetween(DB::raw('date(payment_transactions.created_at)') ,
                [
                    Carbon::parse($request->filterDateFrom)->format('Y-m-d') ,
                    Carbon::parse('today')->format('Y-m-d')
                ]
            );
        }
        if ($request->filterPolicyDateFrom != '-1' && $request->filterPolicyDateto != '-1')
        {
            $query->whereBetween(DB::raw('date(policies.policyActivatedDate)') ,
                [
                    Carbon::parse($request->filterPolicyDateFrom)->format('Y-m-d') ,
                    Carbon::parse($request->filterPolicyDateto)->format('Y-m-d')
                ]
            );
        }
        if ($request->filterPolicyDateFrom != '-1' && $request->filterPolicyDateto == '-1')
        {
            $query->whereBetween(DB::raw('date(policies.policyActivatedDate)') ,
                [
                    Carbon::parse($request->filterPolicyDateFrom)->format('Y-m-d') ,
                    Carbon::parse('today')->format('Y-m-d')
                ]
            );
        }
        if ($request->agent_id != '-1')
        {
            $query->where('policies.agent_id', $request->agent_id);
        }
        if ($request->trans_status != '-1')
        {
            $query->where('payment_transactions.status', 'like', '%' .  $request->trans_status . '%');
        }
       

        if($searchValue != null){
            $totalRecordswithFilter = PaymentTransaction::join('policies','policies.policyNumber','payment_transactions.policyNumber')
                ->join('customer','customer.id','policies.customer_id')
                ->join('products','products.id','policies.product_id')
                ->join('product_plans','product_plans.id','policies.plan_id')
                ->where('payment_transactions.paymentMethod','N-Genius')
                ->where('policies.policyNumber', 'like', '%' .$searchValue . '%')
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
                    'payment_transactions.id',
                    'policies.id as policy_id',
                    'policies.customer_id',
                    'policies.plan_id',
                    'policies.premium',
                    'payment_transactions.amount',
                    'payment_transactions.referenceNumber',
                    'policies.product_id',
                    'policies.storeID',
                    'policies.billing_day',
                    'policies.status as policy_status',
                    'policies.billingStartDate',
                    'policies.first_premium',
                    'policies.premium_freq',
                    'policies.premium',
                    'policies.activation_code',
                    'payment_transactions.policyNumber as policyNumber',
                    'policies.agent_id',
                    'policies.status as policyStatus',
                    'payment_transactions.created_at as created_at',
                    'payment_transactions.note as note',
                    
                    'payment_transactions.status as paymentStatus'
                   
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

            if($record['product_id']){
                $product = Product::where('id',$record['product_id'])->first();
                if($product && $product->name)
                    $productname = $product->name;
                else
                    $productname = 'N/A';
            }else{
                $productname = 'N/A';
            }

            if ($record['premium'] != null)
            {
                $amount =  number_format((float)$record['premium'], 2, '.', '');
            }else{
                $amount = number_format((float)0, 2, '.', '');;
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

            // if ($record['agent_id'] != null)
            // {
            //     $user = User::where('id', $record['agent_id'])
            //         ->first(array(
            //             'firstName',
            //             'lastName'
            //         ));
            //     if ($user != null)
            //         $agentName = ucwords($user->firstName . ' ' . $user->lastName);
            //     else
            //         $agentName = 'N/A';
            // }
            // else
            // {
            //     $agentName = 'N/A';
            // }

            if($record['policy_status'] == 1){
                $p_status = 'Activated';
            }elseif($record['policy_status'] == 0){
                $p_status = 'Deactivated';
            }elseif($record['policy_status'] == 2){
                $p_status = 'Cancelled';
            }else{
                $p_status = 'Not found';
            }

            if($record['first_premium'] != null && $record['premium_freq'] == 1) {
                if($record['premium'] != $record['first_premium'])
                    $fp = $record['first_premium'];
                else
                    $fp = '-';
            }
            else {
                $fp = '-';
            }

            if($record['policyNumber'])
                $policyNumber = $record['policyNumber'];
            else
                $policyNumber = 'N/A';

            // if($record['storeID'] != null) {
            //     $store = Stores::where('id',$record['storeID'])->first();
            //     $storeName = $store->name;
            // }
            // else {
            //     $storeName = '-';
            // }

            if($record['referenceNumber'])
                $referenceNumber = $record['referenceNumber'];
            else
                $referenceNumber = 'N/A';

           

            if ($record['premium_freq'] != null){
                if($record['premium_freq'] == 1){
                    $freq = 'Monthly';
                }
                elseif($record['premium_freq'] == 2){
                    $freq = 'Three Instalments';
                }
                elseif($record['premium_freq'] == 3){
                    $freq = 'Annual';
                }
                else{
                    $freq = 'Frequency not described';
                }


            }elseif($record['premium_freq'] == null && ($record['product_id'] != null && $record['product_id'] != 3))
            {
                $freq = 'Monthly';
            }
            else{
                $freq = 'Frequency not found';
            }

            if($record['billingStartDate'] != null)
                $b_date = $record['billingStartDate'];
            else
                $b_date =  '-';

            if($record['billing_day'] != null)
                $b_day = $record['billing_day'];
            else
                $b_day =  '-';

            if($record['created_at'] != null){
                $authorisionTime = date('d-m-Y h:i A', strtotime($record['created_at']));
            }else{
                $authorisionTime = 'N/A';
            }
            if($record['note'] != null){
                $note = $record['note'];
            }else{
                $note = '-';
            }

            $data_arr[] = array(
                "id" => $id,
                "policyNumber" => $policyNumber,
                "product_name" => $productname,
                "plan_id" => $planname,
                "first_premium" => $fp,
                "amount" => $amount,
                "premium_freq" => $freq,
                "note" => $note,
                //"billing_date" => $b_date,
                //"store" => $storeName,
                //"agent_id" => $agentName,
                "paymentStatus" => $record['paymentStatus'],
               
                "reference" =>$referenceNumber,
                "policy_status" =>$p_status,
                "transaction_Time" =>$authorisionTime

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

}
