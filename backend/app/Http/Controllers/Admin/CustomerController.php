<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Claim;
use AlphaDirect\ClaimReservesCoverage;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Exports\CustomerExport;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Country;
use AplhaDirect\countries;
use AlphaDirect\State;
use AlphaDirect\City;
use AlphaDirect\cities;
use AlphaDirect\CustomerMati;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Product;
use AlphaDirect\sentPolicyDocumentLogs;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\AgentKyc;
use AlphaDirect\Config;
use AlphaDirect\CustomerKycDomCom;
use AlphaDirect\CustomerMatiWebhook;
use AlphaDirect\Http\Controllers\LlmApiCrontroller;
use AlphaDirect\Http\Controllers\WhatsAppController;
use AlphaDirect\Http\Controllers\Payment\VCS\PaymentController;
use Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Hash;
use Http\Client\Exception;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Tag\Input;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Redirect;
use Yajra\DataTables\DataTables;
use AlphaDirect\KycFields;
use AlphaDirect\KycActivityLogs;
use AlphaDirect\KycCompliance;
use AlphaDirect\Models\Company;
use Image;
use Modules\Cashback\Entities\Policies;
use function RedBeanPHP\jsonSerialize;
use PDF;
use File;
use AlphaDirect\Models\OrangeMandate;

class CustomerController extends Controller
{

    /**
     * Show a list of all customer
     *
     * @return View customer index page
     */
    public function index()
    {
        if (auth::user()->hasPermissionTo('customer-list')) {

            return view("admin.customer.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function black_list()
    {
        if (auth::user()->hasPermissionTo('customer-list')) {
            return view("admin.customer.black_list");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function rateLossStats()
    {
        $policyPayment = PaymentTransaction::join('policies', 'payment_transactions.policyNumber', 'policies.policyNumber')
                                            ->where('policies.product_id', 3)->where('payment_transactions.is_refund', 0)
                                            ->where('payment_transactions.status', 'Success')
                                            ->sum('payment_transactions.amount');

        $claimPayment = ClaimReservesCoverage::join('claims', 'claims.id', 'claim_reserves_coverages.claim_id')
                                            ->join('policies', 'policies.id', 'claims.policy_id')
                                            ->where('policies.product_id', 3)
                                            ->first(
                                                array(
                                                  DB::raw('SUM(claim_reserves_coverages.payment_amt) as payment_amt'),
                                                  DB::raw('SUM(claim_reserves_coverages.reserve_amt) as reserve_amt')
                                                )
                                              );
        $totalClaimPayment = $claimPayment->payment_amt + $claimPayment->reserve_amt;
        $return = array();
        $return['P1'] = $policyPayment;
        $return['C1'] = $totalClaimPayment;

        return $return;
    }

    public function getPolicyClaimPayments($policy_id)
    {
        $claimPayment = ClaimReservesCoverage::join('claims', 'claims.id', 'claim_reserves_coverages.claim_id')
                                            ->where('claims.policy_id', $policy_id)
                                            ->first(
                                                array(
                                                  DB::raw('SUM(claim_reserves_coverages.payment_amt) as payment_amt'),
                                                  DB::raw('SUM(claim_reserves_coverages.reserve_amt) as reserve_amt')
                                                )
                                            );
        if($claimPayment->payment_amt == 0)
            return $claimPayment->reserve_amt;
        else
            return $claimPayment->payment_amt;
    }

    //    public function setCustomerGreyBlackStatus($customer_id){
    //        $customerClaims = DB::select(DB::raw('SELECT c.id,p.customer_id,c.policy_id,
    //                p.policyNumber,Date(p.created_at) as policy_created,
    //                Date(c.created_at) as claim_date,
    //                (datediff(Date(c.created_at),
    //                Date(p.created_at))) as diff_in_days
    //                FROM Graphite_dev.policies p
    //                inner join Graphite_dev.claims c
    //                on p.id = c.policy_id where p.customer_id='.$customer_id)
    //        );
    //
    //
    //        $grayList = array();
    //        $blackList = array();
    //        $greenList = array();
    //        $claimYears = array();
    //        $totalPaidAmount = array();
    //        $totalClaimedAmount = array();
    //        $blackListThirdCondition = 0;
    //
    //        if(count($customerClaims) > 0)
    //        {
    //            foreach($customerClaims as $key=>$claims)
    //            {
    //
    //                //Check number of days condition && Check claim years
    //                $year = Carbon::createFromFormat('Y-m-d', $claims->claim_date)->format('Y');
    //                array_push($claimYears,$year);
    //
    //                $paid_amount = Claim::leftJoin('policies','policies.id','claims.policy_id')
    //                    ->leftJoin('payment_transactions','payment_transactions.policyNumber','policies.policyNumber')
    //                    ->where('claims.id',$claims->id)
    //                    ->where('payment_transactions.status','like','%success%')
    //                    ->sum('payment_transactions.amount');
    //
    //                $policy = Claim::leftJoin('policies','policies.id','claims.policy_id')
    //                    ->where('claims.id',$claims->id)
    //                    ->first(array('policies.policyNumber','policies.created_at'));
    //
    //                $amount = ClaimReservesCoverage::where('claim_id',$claims->id)->sum('payment_amt');
    //
    //                $blackListThirdCondition =  Claim::whereBetween('created_at',[Carbon::parse($policy->created_at)->format('Y-m-d'),Carbon::parse($policy->created_at)->addMonths(18)->format('Y-m-d')])->where('customer_id',$claims->customer_id)->count();
    //
    //                array_push($totalClaimedAmount,$amount);
    //                array_push($totalPaidAmount,$paid_amount);
    //
    //                if($claims->diff_in_days <= 35){
    //                    array_push($grayList, $claims->customer_id);
    //                }elseif($claims->diff_in_days > 35 && $claims->diff_in_days <= 365){
    //                    array_push($blackList,$claims->customer_id);
    //                }else{
    //                    array_push($greenList, $claims->customer_id);
    //                }
    //            }
    //        }
    //
    //        //dd(count($customerClaims),$customerClaims,$greenList,$grayList,$blackList);
    //
    //
    //        //$maxIndex = current(array_keys($array, max($array)));
    //      if(count($claimYears) > 0)
    //        $array = array_count_values($claimYears);
    //      else
    //          $array[] = 0;
    //
    //        $maxClaimsValue = max($array);
    //
    //        if(count($totalPaidAmount) > 0)
    //            $maxPaidValue = max($totalPaidAmount);
    //        else
    //            $maxPaidValue = 0;
    //
    //        if($blackListThirdCondition > 0)
    //            array_push($blackList,$customer_id);
    //
    //        if($maxPaidValue > 0)
    //            $percentBenefit = ($maxClaimsValue/$maxPaidValue)*100;
    //        else
    //            $percentBenefit = 0;
    //
    //        if($percentBenefit >= 150 && $percentBenefit <= 999.99){
    //            array_push($grayList,$customer_id);
    //        }elseif($percentBenefit <= 1000){
    //            array_push($blackList,$customer_id);
    //        }else{
    //            array_push($greenList,$customer_id);
    //        }
    //
    //        if(!empty($grayList)){
    //            foreach($grayList as $g){
    //                $cu = Customer::where('id',$g)->first();
    //                $cu->customer_category = 1;
    //                $cu->save();
    //            }
    //        }
    //
    //        if(!empty($blackList)){
    //            foreach($blackList as $b){
    //                $cub = Customer::where('id',$b)->first();
    //                $cub->customer_category = 2;
    //                $cub->save();
    //            }
    //        }
    //
    //        dd($greenList,$grayList,$blackList,$blackListThirdCondition);
    //
    ////        $filteredGray = array_diff($grayList,$greenList);
    ////        $filteredBlack = array_diff($blackList,$grayList);
    ////
    ////        dd($greenList,$grayList,$blackList,$filteredGray,$filteredBlack,$greenList);
    //
    //        //dd($maxClaimsValue,$maxPaidValue,$numberClaims);
    ////        dd($claims->diff_in_days,$grayList,$blackList,$greenList,$claimYears,
    ////            array_count_values($claimYears),$maxClaimsValue,$customerClaims,$totalClaimedAmount,$totalPaidAmount);
    //
    //    }

    public function setCustomerGreyBlackStatus($customer_id)
    {
        $customerClaims = \Illuminate\Support\Facades\DB::select(
            DB::raw('SELECT c.id,p.customer_id,c.policy_id,
                p.policyNumber,Date(p.created_at) as policy_created,
                Date(c.created_at) as claim_date,
                (datediff(Date(c.created_at),
                Date(p.created_at))) as diff_in_days
                FROM policies p
                inner join claims c
                on p.id = c.policy_id where p.customer_id=' . $customer_id)
        );

        $totalPremiumPaid = 0;
        $totalClaimAmount = 0;
        $claimYears = array();
        $reasons = array();
        $claimYearsCount[] = 0;
        $blackListThirdCondition = 0;
        $firstClaim = null;

        if (count($customerClaims) > 0) {
            $firstClaim = $customerClaims[0]->diff_in_days;

            foreach ($customerClaims as $key => $claim) {
                //Total premium recieved from customer
                $paid_amount = Claim::leftJoin('policies', 'policies.id', 'claims.policy_id')
                    ->leftJoin('payment_transactions', 'payment_transactions.policyNumber', 'policies.policyNumber')
                    ->where('claims.id', $claim->id)
                    ->where('payment_transactions.status', 'like', '%success%')
                    ->sum('payment_transactions.amount');

                //total claim amount disbursed to customer
                (int) $amount = ClaimReservesCoverage::where('claim_id', $claim->id)->sum('payment_amt');

                $totalPremiumPaid = $totalPremiumPaid + (int) $paid_amount;
                $totalClaimAmount = $totalClaimAmount + (int) $amount;

                //count for the claims per year
                $year = Carbon::createFromFormat('Y-m-d', $claim->claim_date)->format('Y');
                array_push($claimYears, $year);

                if (count($claimYears) > 0)
                    $claimYearsCount = array_count_values($claimYears);

                //                if($amount != 0)
                //                    dd($amount,$totalPremiumPaid,$totalClaimAmount,count($customerClaims));


            }

            $grayListCondition = Claim::whereBetween('created_at', [Carbon::now()->subMonth(12)->format('Y-m-d'), Carbon::now()->format('Y-m-d')])
                ->where('customer_id', $customer_id)
                ->count();

            $blackListThirdCondition =  Claim::whereBetween('created_at', [Carbon::now()->subMonth(18)->format('Y-m-d'), Carbon::now()->format('Y-m-d')])
                ->where('customer_id', $customer_id)
                ->count();


            if ($totalPremiumPaid != 0)
                $percentBenefit = ceil(($totalClaimAmount / $totalPremiumPaid) * 100);
            else
                $percentBenefit = 0;

            $customer = Customer::where('id', $customer_id)->first();

            if ($grayListCondition == 2) {
                $customer->customer_category = 1;
                $customer->save();

                $reason = 'Two claims in 12 months period';
                array_push($reasons, $reason);
            }

            if ($firstClaim > 35 && $firstClaim <= 365) {
                $customer->customer_category = 1;
                $customer->save();

                $reason = 'First Claim in ' . $firstClaim . ' days';
                array_push($reasons, $reason);
            }

            if ($percentBenefit >= 150 && $percentBenefit < 1000) {
                $customer->customer_category = 1;
                $customer->save();

                $reason = 'Total received amount ' . $percentBenefit . '%';
                array_push($reasons, $reason);
            }

            if ($blackListThirdCondition >= 3) {
                $customer->customer_category = 2;
                $customer->save();

                $reason = $blackListThirdCondition . ' claims in 18 months period ';
                array_push($reasons, $reason);
            }

            if ($firstClaim < 35) {
                $customer->customer_category = 2;
                $customer->save();

                $reason = 'First claim in ' . $firstClaim . ' days';
                array_push($reasons, $reason);
            }

            if ($percentBenefit >= 1000) {
                $customer->customer_category = 2;
                $customer->save();

                $reason = 'Total amount Received ' . $percentBenefit . '%';
                array_push($reasons, $reason);
            }


            if (count($reasons) > 0) {
                $customer->category_reason = serialize($reasons);
                $customer->save();
            }


            //dd($customer_id.'--'.$blackListThirdCondition.' | '.$firstClaim.' | '.$percentBenefit.' | '.$totalClaimAmount.' | '.$totalPremiumPaid);



            //\Log::info($customer_id.'--'.$blackListThirdCondition.' | '.$firstClaim.' | '.$percentBenefit.' | '.$totalClaimAmount.' | '.$totalPremiumPaid);

            //            if($percentBenefit){
            //                dd($customer_id.'-'.$percentBenefit);
            //            }
        }
    }

    public function getWebHook(Request $request)
    {

        return response()->json(['Status' => 'Success', 'Description' => $request->all()], 200);
    }


    /*
        * Pass data through ajax call
        */
    /**
     * @return mixed
     */
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

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // Search value

        // Total records
        $totalRecords = Customer::select('count(*) as allcount')->count();

        // Fetch records
        $records = Customer::orderBy('id', 'DESC');

        // $records->created_at = Carbon::parse($records->created_at)->format('d-m-Y');
        if ($searchValue != null) {
            $cId = Policy::where('policyNumber', 'like', '%' . $searchValue . '%')->pluck( 'customer_id' )->toArray();
            $records->where('id', 'like', '%' . $searchValue . '%')
                ->orWhere('firstName', 'like', '%' . $searchValue . '%')
                ->orWhere('lastName', 'like', '%' . $searchValue . '%')
                ->orWhere('email', 'like', '%' . $searchValue . '%')
                ->orWhereIn('firstName',explode(" ",$searchValue))
                ->orWhereIn('lastName',explode(" ",$searchValue))
                ->orWhereIn('id',$cId);
        }

        if ($searchValue == null) {
            $totalRecordswithFilter = Customer::select('count(*) as allcount')
                ->count();
        } else {
            $totalRecordswithFilter = $records->count();
        }

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get(
                [
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'customer_category',
                    'category_reason',
                    'created_at',
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


            $kyc = KYC::where('customer_id', $record['id'])->first(array('compliance'));

            if ($kyc && $kyc->compliance == 1) {
                $kstatus = '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            } else {
                $kstatus = '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            }

            $actions = '';
            if (auth::user()->can('customer-edit')) {
                $actions .= '<a href="' . route('admin.customer.edit', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            } else {
                $actions .= '<a href="' . route('admin.customer.edit', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
            }
            if (auth::user()->can('customer-delete')) {
                $actions .= '<a href="" value="' . $record['id'] . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
            }

            if ($record['customer_category'] == 1) {
                $cat = 'Gray';
            } elseif ($record['customer_category'] == 2) {
                $cat = 'Black';
            } else {
                $cat = '-';
            }

            $reason = "";
            if ($record['category_reason'] != null) {
                $data = unserialize($record['category_reason'], ['allowed_classes' => false]);
                foreach ($data as $key => $d) {
                    $reason .= '* ' . $d . ' </br>';
                }
            } else {
                $reason = '-';
            }

            $customer_profile = CustomerProfile::where('customer_id', $record['id'])->first(['company_id','entity_type']);
            if(isset($customer_profile) && $customer_profile->company_id != null){
                $company = Company::where('id', $customer_profile->company_id)->first(['id','name']);
            }

            if($customer_profile && $customer_profile->entity_type == 'Organisation'){
                if($company != null && $company->name != null){
                    $name = '<span class="graydot"></span>&nbsp&nbsp'. ucwords(strtolower($company->name));
                }
            }else{
                if($record['customer_category'] == 1){
                    $name = '<span class="graydot"></span>&nbsp&nbsp'. ucwords(strtolower($record['firstName'] .' '. $record['lastName']));
                }elseif($record['customer_category'] == 2){
                    $name = '<span class="blackdot"></span>&nbsp&nbsp'. ucwords(strtolower($record['firstName'] .' '. $record['lastName']));
                }else{
                    $name = '<span class="greendot"></span>&nbsp&nbsp'. ucwords(strtolower($record['firstName'] .' '. $record['lastName']));
                }
            }

            if ($customer_profile && $customer_profile->entity_type != null) {
                $entity_type = $customer_profile->entity_type;
            }else{
                $entity_type = 'NA';
            }

            $data_arr[] = array(
                "id" => $id,
                "fullName" => $name,
                "entity_type" => $entity_type,
                "email" => $record['email'],
                "KYC" => $kstatus,
                "actions" => $actions,
                "customer_category" => $cat,
                "category_reason" => $reason,
                "created_at" => Carbon::parse($record['created_at'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i')
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

        //        $customer = Customer::get(array('id', 'firstName', 'lastName', 'email', 'created_at','customer_category','category_reason'));
        //
        //
        //        return DataTables::of($customer)
        //            ->editColumn('created_at', function ($customer) {
        //                return $customer->created_at;
        //            })
        //            ->editColumn('customer_category', function ($customer) {
        //                if($customer->customer_category == 2){
        //                    return 'Black';
        //                }elseif($customer->customer_category == 1){
        //                    return 'Gray';
        //                }else{
        //                    return '-';
        //                }
        //            })
        //            ->editColumn('category_reason', function ($customer) {
        //                if($customer->customer_category){
        //
        //                    return unserialize($customer->customer_category);
        //                }else{
        //                    return '-';
        //                }
        //            })
        //            ->addColumn('actions', function ($customer) {
        //                $actions = '';
        //                if (auth::user()->can('customer-edit')) {
        //                    $actions .= '<a href="' . route('admin.customer.edit', $customer->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
        //                                <i class="la la-edit"></i>
        //                            </a>';
        //                } else {
        //                    $actions .= '<a href="' . route('admin.customer.edit', $customer->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
        //                                <i class="flaticon-eye"></i>
        //                            </a>';
        //                }
        //                if (auth::user()->can('customer-delete')) {
        //                    $actions .= '<a href="" value="' . $customer->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
        //                                <i class="la la-trash"></i>
        //                            </a>';
        //                }
        //                return $actions;
        //            })
        //            ->rawColumns(['actions','category_reason','customer_category'])
        //            ->make(true);
    }


    /**
     * Show a page to customer create
     *
     * @return View customer create page
     */
    public function block_list_data(Request $request)
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
        $searchValue = $search_arr['value']; // Search value

        // Total records

        $totalRecords = Customer::where('is_blocked', 1)->select('count(*) as allcount')->count();


        // Fetch records
        $records = Customer::where('is_blocked', 1)->orderBy('id', 'DESC');


        // $records->created_at = Carbon::parse($records->created_at)->format('d-m-Y');

        if ($searchValue != null) {
            $records->where(function ($query) use ($searchValue) {
                $query->where('id', 'like', '%' . $searchValue . '%')
                    ->orWhere('firstName', 'like', '%' . $searchValue . '%')
                    ->orWhere('lastName', 'like', '%' . $searchValue . '%')
                    ->orWhere('email', 'like', '%' . $searchValue . '%');
            });
        }


        if ($searchValue == null) {
            $totalRecordswithFilter = Customer::where('is_blocked', 1)->select('count(*) as allcount')
                ->count();
        } else {
            $totalRecordswithFilter = $records->count();
        }

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get(
                [
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'customer_category',
                    'category_reason',
                    'created_at',
                    'is_blocked',
                    'block_reason',
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


            $kyc = KYC::where('customer_id', $record['id'])->first(array('compliance'));

            if ($kyc && $kyc->compliance == 1) {
                $kstatus = '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
            } else {
                $kstatus = '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
            }

            $actions = '';
            if (auth::user()->can('customer-edit')) {
                $actions .= '<a href="' . route('admin.customer.edit', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
            } else {
                $actions .= '<a href="' . route('admin.customer.edit', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
            }



            if ($record['is_blocked'] == 2) {
                $cat = 'No';
            } elseif ($record['is_blocked'] == 1) {
                $cat = 'Yes';
            } else {
                $cat = '-';
            }

            $reason = "";
            if ($record['block_reason'] != null) {
                $data = $record['block_reason'];

                $reason = $data;
            } else {
                $reason = '-';
            }



            $data_arr[] = array(
                "id" => $id,
                "firstName" => ucwords(strtolower($record['firstName'])),
                "lastName" => ucwords(strtolower($record['lastName'])),
                "email" => $record['email'],
                "KYC" => $kstatus,
                "actions" => $actions,
                "is_blocked" => $cat,
                "category_reason" => $reason,
                "created_at" => Carbon::parse($record['created_at'])->format('Y-m-d H:i')
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

        //        $customer = Customer::get(array('id', 'firstName', 'lastName', 'email', 'created_at','customer_category','category_reason'));
        //
        //
        //        return DataTables::of($customer)
        //            ->editColumn('created_at', function ($customer) {
        //                return $customer->created_at;
        //            })
        //            ->editColumn('customer_category', function ($customer) {
        //                if($customer->customer_category == 2){
        //                    return 'Black';
        //                }elseif($customer->customer_category == 1){
        //                    return 'Gray';
        //                }else{
        //                    return '-';
        //                }
        //            })
        //            ->editColumn('category_reason', function ($customer) {
        //                if($customer->customer_category){
        //
        //                    return unserialize($customer->customer_category);
        //                }else{
        //                    return '-';
        //                }
        //            })
        //            ->addColumn('actions', function ($customer) {
        //                $actions = '';
        //                if (auth::user()->can('customer-edit')) {
        //                    $actions .= '<a href="' . route('admin.customer.edit', $customer->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
        //                                <i class="la la-edit"></i>
        //                            </a>';
        //                } else {
        //                    $actions .= '<a href="' . route('admin.customer.edit', $customer->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
        //                                <i class="flaticon-eye"></i>
        //                            </a>';
        //                }
        //                if (auth::user()->can('customer-delete')) {
        //                    $actions .= '<a href="" value="' . $customer->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
        //                                <i class="la la-trash"></i>
        //                            </a>';
        //                }
        //                return $actions;
        //            })
        //            ->rawColumns(['actions','category_reason','customer_category'])
        //            ->make(true);
    }


    /**
     * Show a page to customer create
     *
     * @return View customer create page
     */
    public function create()
    {
        if (auth::user()->hasPermissionTo('customer-create')) {
            $states    = State::where('country_id', 28)->get(['id', 'name']);
            $countries = Country::select('id', 'name')->get();
            return view("admin.customer.create", compact('states', 'countries'));
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }



    /**
     * method to store accounts data from create page.
     *
     * @return View customer index page
     */
    public function store(Request $request)
    {
        $cellphone = isset($request->mobile)?$request->mobile:null;
        $omang = isset($request->omang)?$request->omang:null;
        $passport = isset($request->passport)?$request->passport:null;
        $email = isset($request->cutomer_email)?$request->cutomer_email:null;
        $first_name = isset($request->first_name)?$request->first_name:null;
        $last_name = isset($request->last_name)?$request->last_name:null;

        if ($first_name != null && $last_name != null && Customer::where('is_blocked',1)->where('firstName', $first_name)->where('lastName', $last_name)->exists()) {
            return Redirect::route('admin.customer.index')->with('error', 'Customer in block list');
        }elseif ($cellphone != null && Customer::where('cellphone', $cellphone)->exists()) {
            return Redirect::route('admin.customer.index')->with('error', 'Customer cellphone already exist');
        }elseif($omang != null && CustomerProfile::where('omang', $omang)->exists()){
            return Redirect::route('admin.customer.index')->with('error', 'Customer omang already exist');
        }elseif($passport != null && CustomerProfile::where('passport', $passport)->exists()){
            return Redirect::route('admin.customer.index')->with('error', 'Customer passport already exist');
        }elseif($email != null && Customer::where('email',$email)->exists()){
            return Redirect::route('admin.customer.index')->with('error', 'Customer email already exist');
        }else{


        // DB::beginTransaction();
        try {
            // dd('stop');
            if($request->entity_type=="Organisation"){
                $request->validate([
                    'org_name' => 'required',
                    'vat_reg' => 'required',
                    'reg_no' => 'required',
                    'tax_id' => 'required',
                    'org_phone_no' => 'required'
                ]);
            }else{
                $request->validate([
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'gender' => 'required',
                    'dob' => 'required',
                    'maritalstatus' => 'required',
                    'sourceOfIncome' => 'required'
                ]);
            }

            /*Add Records to Customers table*/
            $customer = new Customer();
            if($request->entity_type=="Person"){
                $customer->firstName = $request->first_name;
                $customer->middleName = $request->middle_name;
                $customer->lastName = $request->last_name;
            }
            $customer->email = $request->cutomer_email;
            $customer->cellphone = $request->mobile;
            $customer->password = Hash::make($request->password);
            $customer->save();
            // dd($customer);
            $ifExists = CustomerProfile::where('customer_id',$customer->id)->exists();
            if(!empty($ifExists))
            {
                $customerProfile = CustomerProfile::where('customer_id',$customer->id)->first();
            }else{
                /*Add Records to Customers Profile table*/
                $customerProfile = new CustomerProfile();
            }
            $customerProfile->customer_id = $customer->id;
            $customerProfile->entity_type = $request->entity_type;
            if($request->entity_type=="Organisation"){
                $customerProfile->org_name = $request->org_name;
                $customerProfile->vat_reg = $request->vat_reg;
                $customerProfile->reg_no = $request->reg_no;
                $customerProfile->tax_id = $request->tax_id;
                $customerProfile->org_phone_no = $request->org_phone_no;
            }else{
                $customerProfile->dob = Carbon::parse($request->dob)->format('Y-m-d');
                $customerProfile->gender = $request->gender;
                $customerProfile->omang = $request->omang;
                $customerProfile->maritalstatus = $request->maritalstatus;
                if(isset($request->passport)){
                   $customerProfile->passport = $request->passport;
                   $customerProfile->countryId = $request->countryId;
                }
                $customerProfile->sourceOfIncome = json_encode($request->sourceOfIncome);
            }

            $customerProfile->address = $request->address;
            $customerProfile->city = $request->city;
            $customerProfile->state = $request->state;
            $customerProfile->save();

            //KYC Update
            $omangKYC = new KYC();
            $omangKYC->customer_id = $customerProfile->customer_id;
         /*   if ($request->hasFile('driving')) {
                $file = $request->file('driving');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/driving_license' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->driving_license = $filePath;
            }
            if ($request->hasFile('omang')) {
                $file = $request->file('omang');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/Omang-front' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->omang = $filePath;
            }
            if ($request->hasFile('omangBack')) {
                $file = $request->file('omangBack');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/Omang-back' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->omangBack = $filePath;
            }
            if ($request->hasFile('residence')) {
                $file = $request->file('residence');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_residence' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->proof_residence = $filePath;
            }
            if ($request->hasFile('income')) {
                $file = $request->file('income');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proof_income' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->proof_income = $filePath;
            }
            if ($request->hasFile('passport')) {
                $file = $request->file('passport');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/passport' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->passport = $filePath;
            }

 */
            $omangKYC->compliance = 0; //0 - Pending , 1 - Compliant and 2 - non compliant

            $saved = $omangKYC->save();
            DB::commit();
            activity('Customer')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Customer Created');
            return Redirect::route('admin.customer.index')->with('success', 'Customer Created Successfully');
        } catch (\Exeption $e) {
            DB::rollBack();
            return Redirect::route('admin.customer.index')->with('error', $e->getMessage());
        }
    }
    }


    /**
     * Show a page to edit specific customer
     * param: customer id ($id)
     * @return View customer view page
     */
    public function edit($id)
    {
        $customer = Customer::where('id', $id)->first();
        $customerProfile = CustomerProfile::where('customer_id', $id, 'countryId')->first();
        // dd($customerProfile->org_name);
        $countries = Country::select('id', 'name')->get();
        $i = '';
        $incomeSource = null;
       // dd($customerProfile->sourceOfIncome);
        if ($customerProfile->sourceOfIncome != null && ($customerProfile->sourceOfIncome)) {
             if($customerProfile->sourceOfIncome == "unemployed"){
                $incomeSource = $customerProfile->sourceOfIncome;
             }else{
            foreach ($customerProfile->sourceOfIncome as $key => $i) {

                $incomeSource = $key;
                $i = $i;

            }
        }
        } else {
            $incomeSource = $customerProfile->sourceOfIncome;
        }


        // $states = State::select('id', 'name')->get();
        // $cities = City::select('id', 'name')->get();
        // $customerCountry = CustomerProfile::pluck('countryId')->first();
        $cities = NULL;
        $states    = State::where('country_id', 28)->get(['id', 'name']);
        if (isset($customerProfile->state)) {
            $cities    = City::where('state_id', $customerProfile->state)->get(['id', 'name']);
        }
        $reason = "";
            if ($customer['category_reason'] != null) {
                $data = unserialize($customer['category_reason'], ['allowed_classes' => false]);
                foreach ($data as $key => $d) {
                    $reason .=  $d;
                }
            } else {
                $reason = '';
            }

        $kyc = KYC::where('customer_id',  $customer->id)->first();
        if (auth::user()->hasPermissionTo('customer-edit')) {
            return view('admin.customer.edit', compact('customer', 'incomeSource', 'customerProfile', 'countries', 'cities', 'states', 'kyc','reason','i'));
        } elseif (auth::user()->can('customer-list')) {
            return view('admin.customer.view', compact('customer', 'incomeSource', 'customerProfile', 'kyc','reason','i'));
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function add_to_black_list()
    {
        return view('admin.customer.add_to_black_list');
    }

    public function add_to_block_list_update(Request $request)
    {

        $is_blocked = 1;
        $customer_category = 2;
        $customer = new Customer();
        $customer->firstName = $request->first_name;
        $customer->lastName = $request->last_name;
        if (isset($request->middle_name)) {
            $customer->middleName = $request->middle_name;
        }
        if (isset($request->email)) {
            $customer->email = $request->email;
        }
        if (isset($request->mobile)) {
            $customer->cellphone = $request->mobile;
        }
        // $customer->omang = $request->omang;
        // $customer->passport = $request->passport;

        $customer->customer_category = $customer_category;

        $customer->is_blocked = $is_blocked;
        $customer->block_reason = $request->block_reason;
        $customer->save();

        $ifExists = CustomerProfile::where('customer_id',$customer->id)->exists();
        if(!empty($ifExists))
        {
            $customerProfile = CustomerProfile::where('customer_id',$customer->id)->first();
        }else{
            /*Add Records to Customers Profile table*/
            $customerProfile = new CustomerProfile();
        }

        $customerProfile->customer_id = $customer->id;
        $customerProfile->dob = "2002-11-01";
        $customerProfile->gender = 1;
        if (isset($request->omang)) {
            $customerProfile->omang = $request->omang;
        }
        if (isset($request->passport)) {
            $customerProfile->passport = $request->passport;

        }
        $customerProfile->address = "test";
        $customerProfile->city = "Moiyabana";
        $customerProfile->state = 491;
        $customerProfile->save();

        return Redirect::route('admin.customer.black_list')->with('success', 'Customer Added to black list Successfully');
    }
    public function update($id, Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->entity_type=="Organisation"){
                $request->validate([
                    'org_name' => 'required',
                    'vat_reg' => 'required',
                    'tax_id' => 'required',
                    'reg_no' => 'required',
                    'org_phone_no' => 'required'
                ]);
            }else{
                $request->validate([
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'gender' => 'required',
                   /* 'dob' => 'required', */
                    'maritalstatus' => 'required',
                    'sourceOfIncome' => 'required'
                ]);
            }

            $reasons = array();
            $customer = Customer::where('id', $id)->first();
            if($request->entity_type=="Person"){
                $customer->firstName = $request->first_name;
                $customer->lastName = $request->last_name;
                $customer->middleName = $request->middle_name;
            }
            $customer->email = $request->cutomer_email;
            if ($request->password != null) {
                $customer->password = Hash::make($request->password);
            }
            $customer->cellphone = $request->mobile;
            $customer->is_blocked = $request->is_blocked;
            $customer->block_reason = $request->block_reason;
            $customer->customer_category = $request->customer_category;
            $reason = $request->category_reason;
            array_push($reasons, $reason);
            $customer->category_reason = serialize($reasons);
            $customer->save();
            $customerProfile = CustomerProfile::where('customer_id', $id)->first();
            $customerProfile->customer_id = $customer->id;
            $customerProfile->entity_type = $request->entity_type;
            if($request->entity_type=="Organisation"){
                $customerProfile->org_name = $request->org_name;
                $customerProfile->vat_reg = $request->vat_reg;
                $customerProfile->reg_no = $request->reg_no;
                $customerProfile->tax_id = $request->tax_id;
                $customerProfile->org_phone_no = $request->org_phone_no;
            }else{
              if ( \AlphaDirect\Policy::where("customer_id", $customer->id)
                ->where('product_id', 1)
                ->where('status', '!=', 2)
                ->exists()
                && !\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('adi_customer_dob_change') ){

                }else{
                    $customerProfile->dob = Carbon::parse($request->dob)->format('Y-m-d');
                }


                $customerProfile->gender = $request->gender;
                $customerProfile->maritalstatus = $request->maritalstatus;
                $customerProfile->sourceOfIncome = json_encode($request->sourceOfIncome);
                $customerProfile->omang = $request->omang;
                $customerProfile->passport = $request->passport;
                $customerProfile->countryId = $request->country;
            }
            $customerProfile->address = $request->address;
            $customerProfile->city = $request->city;
            $customerProfile->state = $request->state;
            // dd($customerProfile);
            $customerProfile->save();

            //KYC Update
            $omangKYC = KYC::where('customer_id', $id)->first();
           if ($omangKYC == null) {
                $omangKYC = new KYC();
                $omangKYC->customer_id = $id;
            }

          /*   if ($request->hasFile('driversLicense')) {
                $file = $request->file('driversLicense');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/driving_license' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->driving_license = $filePath;
            }
            if ($request->hasFile('omangFront')) {
                $file = $request->file('omangFront');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omang-front' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->omang = $filePath;
            }

            if ($request->hasFile('omangBack')) {
                $file = $request->file('omangBack');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/omang-back' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->omangBack = $filePath;
            }

            if ($request->hasFile('proofResidence')) {
                $file = $request->file('proofResidence');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/proofResidence' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->proof_residence = $filePath;
            }
            if ($request->hasFile('proofIncome')) {
                $file = $request->file('proofIncome');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/income' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->proof_income = $filePath;
            }
            if ($request->hasFile('passportImg')) {
                $file = $request->file('passportImg');
                $name = $file->getClientOriginalName();
                $filePath = 'MIS/' . $customer->id . '/' . 'KYC' . '/' . $customer->firstName . '/passport' . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $omangKYC->passport = $filePath;
            }
            */


            /*if ($omangKYC->driving_license && $omangKYC->proof_residence && $omangKYC->proof_income && (($omangKYC->omang && $omangKYC->omangBack) || $omangKYC->passport)) {
                $omangKYC->compliance = 1;
            } else {
                $omangKYC->compliance = 0;
            }*/

            //$omangKYC->compliance = 0; //0 - Pending , 1 - Compliant and 2 - non compliant

            $omangKYC->save();
            DB::commit();
            activity('Customer')
                ->performedOn($customer)
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Customer Updated');
            return Redirect::route('admin.customer.index')->with('success', 'Customer Updated Successfully');
        } catch (\Exeption $e) {
            DB::rollBack();
            return Redirect::route('admin.customer.index')->with('error', $e->getMessage());
        }
    }

    /**
     * method to return modal body for confirm-delete.
     *
     * @return View json
     */
    public function getModalDelete(Request $request)
    {
        $policy = Policy::where('customer_id', $request->get('id'))->get();
        $check = $policy->count();
        if ($check > 1) {
            $body = 'Customer holds multiple policy.  Can not delete this Customer ' . '<br>';
            foreach ($policy as $p) {
                $body .= 'Policy Number : <a href="' . route('admin.policy.edit', $p->id) . '">' . $p->policyNumber . '</a>.<br>';
            }
            return response()->json(['status' => 'error', 'body' => $body]);
        } elseif ($check == 1) {
            foreach ($policy as $p) {
                $body = 'Customer holds Policy number:  <a href="' . route('admin.policy.edit', $p->id) . '">' . $p->policyNumber . '</a>.<br> Can not delete this Customer' . '<br>';
            }
            return response()->json(['status' => 'error', 'body' => $body]);
            // Prepare the error message
        } else {
            $body = 'Are you sure you want to delete the Customer ?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);
        }
    }


    /**
     *deletes specific customer accounts
     *param: customer id ($id)
     * @return customer index page
     */
    public function destroy($id)
    {
        try {
            activity('Customer')
                ->performedOn(Customer::where('id', $id)->first())
                ->causedBy(Customer::where('id', auth()->user()->id)->first())
                ->log('Customer Deleted');
            Customer::where('id', $id)->delete();
            CustomerProfile::where('Customer_id', $id)->delete();
            return Redirect::route('admin.customer.index')->with('success', 'Customer Deleted Successfully');
        } catch (Exception $e) {
            return Redirect::route('admin.customer.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * sets password for customer
     *param: cellphone id
     * @return json
     */
    public function setPassword(Request $request)
    {
        $phoneNumber = $request->cellphone;
        $password = $request->password;
        try {
            //code...
            if ($phoneNumber != null && $password != null) {
                $customer = Customer::where('cellphone', $phoneNumber)->first();
                $customer->password = Hash::make($request->password);
                if ($customer->save()) {
                    return response()->json(['status' => 200]);
                } else {
                    return response()->json(['status' => 400]);
                }
            } else {
                return response()->json(['message' => 'Number or passsword field empty']);
            }
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()]);
        }
    }


    public function createOdooPartnerGraphite($id, $customerName, $email, $phone)
    {

        $customerProfile = CustomerProfile::where('customer_id', $id)->first();
        $client = new \GuzzleHttp\Client();
        $url = "http://13.244.123.13/CreatePartner";
        $requestContent = [
            'form_params' => [
                'name' => $customerName,
                'display_name' => $customerName,
                'type' => 'contact',
                'street' => $customerProfile->address,
                'street2' => $customerProfile->street2,
                'city' => $customerProfile->city,
                'country_id' => 35,
                'vat' => $customerProfile->vat,
                'email' => $customerProfile->email,
                'phone' => $customerProfile->phone,
                'is_company' => $customerProfile->is_company,
                'company_type' => $customerProfile->company_type,
            ],
        ];

        try {
            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();

            $customer = Customer::where('id', $id)->first();
            $customer->odoo_customer_id = $response;
            $customer->save();
            return $response;
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function createOdooPartnerGFS(Request $request)
    {

        $customer = new Customer();
        $customer->firstName = $request->firstName;
        $customer->middleName = $request->middleName;
        $customer->lastName = $request->lastName;
        $customer->email = $request->email;
        $customer->cellphone = $request->cellphone;
        $customer->is_gfs = true;
        $customer->gfs_customer_id = $request->customer_id;
        $customer->save();

        $ifExists = CustomerProfile::where('customer_id',$customer->id)->exists();
        if(!empty($ifExists))
        {
            $customerProfile = CustomerProfile::where('customer_id',$customer->id)->first();
        }else{
            /*Add Records to Customers Profile table*/
            $customerProfile = new CustomerProfile();
        }
        $customerProfile->customer_id = $customer->id;
        $customerProfile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
        $customerProfile->gender = $request->gender;
        $customerProfile->omang = $request->omang;
        $customerProfile->passport = $request->passport;
        $customerProfile->street = $request->street;
        $customerProfile->street2 = $request->street2;
        $customerProfile->city = $request->city;
        $customerProfile->vat = $request->vat;
        $customerProfile->is_company = $request->is_company;
        $customerProfile->company_type = $request->company_type;
        $customerProfile->save();


        $client = new \GuzzleHttp\Client();
        $url = "http://13.244.123.13/CreatePartner";
        $requestContent = [
            'form_params' => [
                'name' => $request->name,
                'display_name' => $request->name,
                'type' => 'contact',
                'street' => $request->address,
                'street2' => $request->street2,
                'city' => $request->city,
                'country_id' => 35,
                'vat' => $request->vat,
                'email' => $request->email,
                'phone' => $request->phone,
                'is_company' => $request->is_company,
                'company_type' => $request->company_type,
            ],
        ];

        try {
            $apiRequest = $client->request('POST', $url, $requestContent);
            $response = $apiRequest->getBody()->getContents();
            if (is_numeric($response)) {
                $customer->odoo_customer_id = $response;
                $customer->save();
            }
            return response()->json([
                "code" => "200",
                "message" => "User created succesfully"
            ]);
        } catch (RequestException $re) {
            // For handling exception.
            return response()->json([
                "code" => "401",
                "message" => "An error has occured",
            ]);
        }
    }

    public function verifyKycAdv(Request $request)
    {
        $policy = Policy::where('policyNumber', $request->policyNumber)->first();
        //get product
        $product_data = Product::join('kyc_compliance', 'kyc_compliance.id', 'products.kyc_compliance')
            ->where('products.id', $policy->product_id)
            ->first();
        $fieldsData = json_decode($product_data->fields);
        #echo "<pre>"; print_r($fieldsData); die('end');
        $kycfields = array();
        foreach ($fieldsData as $fieldSet) {

            if ($fieldSet->check != 3) {
                $customer_kyc_column = KycFields::where('name', $fieldSet->field)->first();

                if ($fieldSet->check == 1) {
                    $kycfields[$customer_kyc_column->customer_kyc_column]=$customer_kyc_column->customer_kyc_column;
                }

                if ($fieldSet->check == 2) {
                    $customer_OtherKyc_column = KycFields::where('name', $fieldSet->other)->first();
                    $kycfields[$customer_kyc_column->customer_kyc_column] = $customer_OtherKyc_column->customer_kyc_column;
                }
            }
        }

        $statusKeysArr = array("omang" => "omangFrontStatus", "omangBack" => "omangBackStatus","passport"=>"passportStatus", "driving_license" => "driving_licenseStatus", "proof_residence" => "proof_residenceStatus", "proof_income" => "proof_incomeStatus");
        #dd($kycfields);
        if(!empty($kycfields)){
           $str = "Select * from customer_kyc where customer_id = {$policy->customer_id} and ";
            $i = 0;
            foreach($kycfields as $key=>$value){
                if($key == $value){
                    $str .= "({$key} is not null )  ";
                }else{
                    $str .= "({$key} is not null or {$value} is not null)  ";
                }

                if(++$i!= count($kycfields)) {
                    $str .= " and ";
                  }
            }

            $checkNull = DB::select( $str );
           if(!empty($checkNull)){
            $str2 = "Select * from customer_kyc where customer_id = {$policy->customer_id} and ";
            $j= 0;
            foreach($kycfields as $k=>$v){
                if($statusKeysArr[$k] == $statusKeysArr[$v]){
                    $str2 .= "({$statusKeysArr[$k]} = 1 )  ";
                }else{
                    $str2 .= "({$statusKeysArr[$k]} = 1 or {$statusKeysArr[$v]} = 1)  ";
                }

                if(++$j!= count($kycfields)) {
                    $str2 .= " and ";
                  }
            }
            $checkStatus = DB::select( $str2 );
            if(!empty($checkStatus)){
                return "Approved";
            }
        }else{
            return "notApproved";
        }

        return "notApproved";

        }


        /*if(!empty($mandatory_arr)){
            $str = "Select * from customer_kyc where customer_id = {$policy->customer_id} and ";
             $i = 0;
             foreach($mandatory_arr as $value){
                 $str .= "({$value} is not null) and $statusKeysArr[$value] = 1 ";
                 if(++$i!= count($mandatory_arr)) {
                     $str .= " and ";
                   }
             }
             dd($str);
         } */

    }


    public function getKycComplianceStatus($data, $policies)
    {
         // check compliance
        try {
           // dd($data, $policies);
            $kycFlag = false;
            $kycStatus = array();
            $compstatus = array();

            foreach ($policies as $key => $value) {

                /*$products = Product::join('kyc_compliance', 'kyc_compliance.id', 'products.kyc_compliance')
                    ->where('products.id', $value['product_id'])
                    ->orderBy('products.id', 'DESC')
                    ->get(
                        array(
                            'products.id',
                            'products.name',
                            'products.kyc_compliance',
                            'kyc_compliance.fields'
                        )
                    );

                if (isset($products)) {
                    foreach ($products as $key => $product) {
                        if (isset($product) && isset($product->fields)) {
                            $fieldsData = json_decode($product->fields);
                            if (isset($fieldsData)) {

                                $documentStatusCheck = KYC::where('customer_id', $value['customer_id']);

                                $documentStatusCheck = $documentStatusCheck->where(function ($q) use ($fieldsData, $documentStatusCheck, $data) {

                                    $documentStatusCheck->where(function ($q) use ($fieldsData, $documentStatusCheck, $data) {

                                        $documentStatusCheck->where(function ($q) use ($fieldsData, $documentStatusCheck, $data) {

                                            foreach ($fieldsData as $key => $field) {
                                                if ($field->check == 1) {
                                                    $documentName = KycFields::where('name', $field->field)
                                                        ->orwhere('name', $field->other)
                                                        ->get(array('customer_kyc_column'));


                                                    switch ($documentName[0]->customer_kyc_column) {
                                                        case 'omang':
                                                            $q->where('omangFrontStatus', '!=', 1);
                                                            if ($data->omangFrontStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'omangFrontStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'omangBack':
                                                            $q->where('omangBackStatus', '!=', 1);
                                                            if ($data->omangBackStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'omangBackStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'driving_license':
                                                            $q->where('driving_licenseStatus', '!=', 1);
                                                            if ($data->driving_licenseStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'driving_licenseStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'proof_residence':
                                                            $q->where('proof_residenceStatus', '!=', 1);
                                                            if ($data->proof_residenceStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'proof_residenceStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'proof_income':
                                                            $q->where('proof_incomeStatus', '!=', 1);
                                                            if ($data->proof_incomeStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'proof_incomeStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'passport':
                                                            $q->where('passportStatus', '!=', 1);
                                                            if ($data->passportStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'passportStatus'
                                                                );
                                                            }
                                                            break;

                                                        default:
                                                            break;
                                                    }
                                                }
                                            }

                                            $q->orWhere(function ($r) use ($fieldsData, $documentStatusCheck, $data) {
                                                foreach ($fieldsData as $key => $field) {
                                                    if ($field->check == 1) {
                                                        $documentName = KycFields::where('name', $field->field)
                                                            ->orwhere('name', $field->other)
                                                            ->get(array('customer_kyc_column'));


                                                        switch ($documentName[0]->customer_kyc_column) {
                                                            case 'omang':
                                                                $r->orWhere('omangFrontStatus', 0);
                                                                if ($data->omangFrontStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'omangFrontStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'omangBack':
                                                                $r->orWhere('omangBackStatus', 0);
                                                                if ($data->omangBackStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'omangBackStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'driving_license':
                                                                $r->orWhere('driving_licenseStatus', 0);
                                                                if ($data->driving_licenseStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'driving_licenseStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'proof_residence':
                                                                $r->orWhere('proof_residenceStatus', 0);
                                                                if ($data->proof_residenceStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'proof_residenceStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'proof_income':
                                                                $r->orWhere('proof_incomeStatus', 0);
                                                                if ($data->proof_incomeStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'proof_incomeStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'passport':
                                                                $r->orWhere('passportStatus', 0);
                                                                if ($data->passportStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'passportStatus'
                                                                    );
                                                                }
                                                                break;

                                                            default:
                                                                break;
                                                        }
                                                    }
                                                }
                                            });

                                            $q->orWhere(function ($s) use ($fieldsData, $documentStatusCheck, $data) {
                                                foreach ($fieldsData as $key => $field) {
                                                    if ($field->check == 2) {
                                                        $documentName = KycFields::where('name', $field->field)
                                                            ->orwhere('name', $field->other)
                                                            ->get(array('customer_kyc_column'));
                                                        // $q->addSelect(
                                                        //     $documentName[0]->customer_kyc_column
                                                        // );
                                                        if (isset($documentName) && isset($documentName[1]->customer_kyc_column)) {
                                                            switch ($documentName[0]->customer_kyc_column) {
                                                                case 'omang':
                                                                    $s->where('omangFrontStatus', 0);
                                                                    if ($data->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'omangBack':
                                                                    $s->where('omangBackStatus', 0);
                                                                    if ($data->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'driving_license':
                                                                    $s->where('driving_licenseStatus', 0);
                                                                    if ($data->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_residence':
                                                                    $s->where('proof_residenceStatus', 0);
                                                                    if ($data->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_income':
                                                                    $s->where('proof_incomeStatus', 0);
                                                                    if ($data->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'passport':
                                                                    $s->where('passportStatus', 0);
                                                                    if ($data->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }

                                                            switch ($documentName[1]->customer_kyc_column) {
                                                                case 'omang':
                                                                    $s->where('omangFrontStatus', 0);
                                                                    if ($data->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'omangBack':
                                                                    $s->where('omangBackStatus', 0);
                                                                    if ($data->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'driving_license':
                                                                    $s->where('driving_licenseStatus', 0);
                                                                    if ($data->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_residence':
                                                                    $s->where('proof_residenceStatus', 0);
                                                                    if ($data->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_income':
                                                                    $s->where('proof_incomeStatus', 0);
                                                                    if ($data->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'passport':
                                                                    $s->where('passportStatus', 0);
                                                                    if ($data->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }
                                                        } else {
                                                            switch ($documentName[0]->customer_kyc_column) {
                                                                case 'omang':
                                                                    $documentStatusCheck->where('omangFrontStatus', 0);
                                                                    if ($data->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'omangBack':
                                                                    $documentStatusCheck->where('omangBackStatus', 0);
                                                                    if ($data->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'driving_license':
                                                                    $documentStatusCheck->where('driving_licenseStatus', 0);
                                                                    if ($data->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_residence':
                                                                    $documentStatusCheck->where('proof_residenceStatus', 0);
                                                                    if ($data->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_income':
                                                                    $documentStatusCheck->where('proof_incomeStatus', 0);
                                                                    if ($data->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'passport':
                                                                    $documentStatusCheck->where('passportStatus', 0);
                                                                    if ($data->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                        });
                                    });
                                });
                            }
                        }
                    }
                }*/
                $policy = Policy::where('policyNumber', $value->policyNumber)->first();
                $forMotor =    Policy::where('customer_id', $policy->customer_id)->where('product_id',3)->where('status',1)->first();
                if($forMotor){
                    $policy = Policy::where('policyNumber', $forMotor->policyNumber)->first();
                }
                //get product
                $product_data = Product::join('kyc_compliance', 'kyc_compliance.id', 'products.kyc_compliance')
                    ->where('products.id', $policy->product_id)
                    ->first();
                $fieldsData = json_decode($product_data->fields);
                #echo "<pre>"; print_r($fieldsData); die('end');
                $kycfields = array();
                foreach ($fieldsData as $fieldSet) {

                    if ($fieldSet->check != 3) {
                        $customer_kyc_column = KycFields::where('name', $fieldSet->field)->first();

                        if ($fieldSet->check == 1) {
                            $kycfields[$customer_kyc_column->customer_kyc_column]=$customer_kyc_column->customer_kyc_column;
                        }

                        if ($fieldSet->check == 2) {
                            $customer_OtherKyc_column = KycFields::where('name', $fieldSet->other)->first();
                            $kycfields[$customer_kyc_column->customer_kyc_column] = $customer_OtherKyc_column->customer_kyc_column;
                        }
                    }
                }

                $statusKeysArr = array("omang" => "omangFrontStatus", "omangBack" => "omangBackStatus","passport"=>"passportStatus", "driving_license" => "driving_licenseStatus", "proof_residence" => "proof_residenceStatus", "proof_income" => "proof_incomeStatus", "passport_back" => "passportBack");

                $documentStatusCheck = NULL;
                if(!empty($kycfields)){
                   $str = "Select * from customer_kyc where customer_id = {$policy->customer_id} and ";
                    $i = 0;
                    foreach($kycfields as $key=>$value){
                        if($key == $value){
                            $str .= "({$key} is not null )  ";
                        }else{
                            $str .= "({$key} is not null or {$value} is not null)  ";
                        }

                        if(++$i!= count($kycfields)) {
                            $str .= " and ";
                          }
                    }

                    $checkNull = DB::select( $str );
                    if(!empty($checkNull)){
                        $str2 = "Select * from customer_kyc where customer_id = {$policy->customer_id} and ";
                        $j= 0;
                        foreach($kycfields as $k=>$v){
                            if($statusKeysArr[$k] == $statusKeysArr[$v]){
                                $str2 .= "({$statusKeysArr[$k]} = 1 )  ";
                            }else{
                                $str2 .= "({$statusKeysArr[$k]} = 1 or {$statusKeysArr[$v]} = 1)  ";
                            }

                            if(++$j!= count($kycfields)) {
                                $str2 .= " and ";
                            }
                        }
                        //dd($str2);
                        $documentStatusCheck = DB::select( $str2 );

                    }
                }

            }
            if (!empty($documentStatusCheck)) {
                $reason = '';
                $reason .= 'Approved';
                $data->status = 'Approve';
                $data->approved_date = Carbon::now();
                $data->reason = $reason;
                $data->save();

                $compstatus['compliance'] = 1;
                $compstatus['product_id'] = $policy->product_id;

                return $compstatus;  // 1- compliant , 2 - non-compliant // if empty customers then compliant else non-compliant
            } else {
                $data->approved_date = NULL;
                $reason = '';
                $reason .= 'Your KYC document(s) is unapproved due to the following reason : <br>';
                $reasonKyc = '';
                $getDocList = $this->getDocList($policy->customer_id,$policy->product_id,$data);

                foreach ($getDocList as $key => $doc) {

                    foreach ($doc as $key) {
                        $remarkCol = '';
                        $docCOl = '';
                        $docName = '';

                        $remarkCol = str_replace('Status', 'Remark', $key);
                        $docCOl = strstr($key, 'Status', true);
                        if ($key == 'omangFrontStatus') {
                            $docCOl = strstr($key, 'FrontStatus', true);
                        }
                        $docName = str_replace('_', ' ', $docCOl);
                        if (!empty($data->$remarkCol)) {
                            $reasonKyc .= $docName . " : " . $data->$remarkCol . "<br>";
                        } elseif ($data->$docCOl == null) {
                            $reasonKyc .= $docName . " : Not Uploaded<br>";
                        } else {
                            $reasonKyc .=  $docName . ': Reason  Not Mentioned' . "<br>";
                        }
                    }
                }

                $reason = $reason . $reasonKyc;
                $data->status = 'rejected';
                $data->reason = $reason;
                $data->save();

                $compstatus['compliance'] = 2;
                $compstatus['product_id'] = $policy->product_id;
               // dd($compstatus);
                return $compstatus; // 1- compliant , 2 - non-compliant // if empty customers then compliant else non-compliant
            }
        } catch (\Exception $e) {
           // dd($e);
            return $e;
        }
    }

    public function getDocList($customer_id,$product_id,$data){
        $products = Product::join('kyc_compliance', 'kyc_compliance.id', 'products.kyc_compliance')
                    ->where('products.id', $product_id)
                    ->orderBy('products.id', 'DESC')
                    ->get(
                        array(
                            'products.id',
                            'products.name',
                            'products.kyc_compliance',
                            'kyc_compliance.fields'
                        )
                    );

                if (isset($products)) {
                    foreach ($products as $key => $product) {
                        if (isset($product) && isset($product->fields)) {
                            $fieldsData = json_decode($product->fields);
                            if (isset($fieldsData)) {

                                $documentStatusCheck = KYC::where('customer_id', $customer_id);

                                $documentStatusCheck = $documentStatusCheck->where(function ($q) use ($fieldsData, $documentStatusCheck, $data) {

                                    $documentStatusCheck->where(function ($q) use ($fieldsData, $documentStatusCheck, $data) {

                                        $documentStatusCheck->where(function ($q) use ($fieldsData, $documentStatusCheck, $data) {

                                            foreach ($fieldsData as $key => $field) {
                                                if ($field->check == 1) {
                                                    $documentName = KycFields::where('name', $field->field)
                                                        ->orwhere('name', $field->other)
                                                        ->get(array('customer_kyc_column'));


                                                    switch ($documentName[0]->customer_kyc_column) {
                                                        case 'omang':
                                                            $q->where('omangFrontStatus', '!=', 1);
                                                            if ($data->omangFrontStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'omangFrontStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'omangBack':
                                                            $q->where('omangBackStatus', '!=', 1);
                                                            if ($data->omangBackStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'omangBackStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'driving_license':
                                                            $q->where('driving_licenseStatus', '!=', 1);
                                                            if ($data->driving_licenseStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'driving_licenseStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'proof_residence':
                                                            $q->where('proof_residenceStatus', '!=', 1);
                                                            if ($data->proof_residenceStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'proof_residenceStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'proof_income':
                                                            $q->where('proof_incomeStatus', '!=', 1);
                                                            if ($data->proof_incomeStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'proof_incomeStatus'
                                                                );
                                                            }
                                                            break;

                                                        case 'passport':
                                                            $q->where('passportStatus', '!=', 1);
                                                            if ($data->passportStatus == 0) {
                                                                $documentStatusCheck->addSelect(
                                                                    'passportStatus'
                                                                );
                                                            }
                                                            break;

                                                        default:
                                                            break;
                                                    }
                                                }
                                            }

                                            $q->orWhere(function ($r) use ($fieldsData, $documentStatusCheck, $data) {
                                                foreach ($fieldsData as $key => $field) {
                                                    if ($field->check == 1) {
                                                        $documentName = KycFields::where('name', $field->field)
                                                            ->orwhere('name', $field->other)
                                                            ->get(array('customer_kyc_column'));


                                                        switch ($documentName[0]->customer_kyc_column) {
                                                            case 'omang':
                                                                $r->orWhere('omangFrontStatus', 0);
                                                                if ($data->omangFrontStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'omangFrontStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'omangBack':
                                                                $r->orWhere('omangBackStatus', 0);
                                                                if ($data->omangBackStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'omangBackStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'driving_license':
                                                                $r->orWhere('driving_licenseStatus', 0);
                                                                if ($data->driving_licenseStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'driving_licenseStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'proof_residence':
                                                                $r->orWhere('proof_residenceStatus', 0);
                                                                if ($data->proof_residenceStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'proof_residenceStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'proof_income':
                                                                $r->orWhere('proof_incomeStatus', 0);
                                                                if ($data->proof_incomeStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'proof_incomeStatus'
                                                                    );
                                                                }
                                                                break;

                                                            case 'passport':
                                                                $r->orWhere('passportStatus', 0);
                                                                if ($data->passportStatus == 0) {
                                                                    $documentStatusCheck->addSelect(
                                                                        'passportStatus'
                                                                    );
                                                                }
                                                                break;

                                                            default:
                                                                break;
                                                        }
                                                    }
                                                }
                                            });

                                            $q->orWhere(function ($s) use ($fieldsData, $documentStatusCheck, $data) {
                                                foreach ($fieldsData as $key => $field) {
                                                    if ($field->check == 2) {
                                                        $documentName = KycFields::where('name', $field->field)
                                                            ->orwhere('name', $field->other)
                                                            ->get(array('customer_kyc_column'));
                                                        // $q->addSelect(
                                                        //     $documentName[0]->customer_kyc_column
                                                        // );
                                                        if (isset($documentName) && isset($documentName[1]->customer_kyc_column)) {
                                                            switch ($documentName[0]->customer_kyc_column) {
                                                                case 'omang':
                                                                    $s->where('omangFrontStatus', 0);
                                                                    if ($data->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'omangBack':
                                                                    $s->where('omangBackStatus', 0);
                                                                    if ($data->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'driving_license':
                                                                    $s->where('driving_licenseStatus', 0);
                                                                    if ($data->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_residence':
                                                                    $s->where('proof_residenceStatus', 0);
                                                                    if ($data->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_income':
                                                                    $s->where('proof_incomeStatus', 0);
                                                                    if ($data->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'passport':
                                                                    $s->where('passportStatus', 0);
                                                                    if ($data->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }

                                                            switch ($documentName[1]->customer_kyc_column) {
                                                                case 'omang':
                                                                    $s->where('omangFrontStatus', 0);
                                                                    if ($data->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'omangBack':
                                                                    $s->where('omangBackStatus', 0);
                                                                    if ($data->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'driving_license':
                                                                    $s->where('driving_licenseStatus', 0);
                                                                    if ($data->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_residence':
                                                                    $s->where('proof_residenceStatus', 0);
                                                                    if ($data->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_income':
                                                                    $s->where('proof_incomeStatus', 0);
                                                                    if ($data->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'passport':
                                                                    $s->where('passportStatus', 0);
                                                                    if ($data->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }
                                                        } else {
                                                            switch ($documentName[0]->customer_kyc_column) {
                                                                case 'omang':
                                                                    $documentStatusCheck->where('omangFrontStatus', 0);
                                                                    if ($data->omangFrontStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangFrontStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'omangBack':
                                                                    $documentStatusCheck->where('omangBackStatus', 0);
                                                                    if ($data->omangBackStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'omangBackStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'driving_license':
                                                                    $documentStatusCheck->where('driving_licenseStatus', 0);
                                                                    if ($data->driving_licenseStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'driving_licenseStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_residence':
                                                                    $documentStatusCheck->where('proof_residenceStatus', 0);
                                                                    if ($data->proof_residenceStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_residenceStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'proof_income':
                                                                    $documentStatusCheck->where('proof_incomeStatus', 0);
                                                                    if ($data->proof_incomeStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'proof_incomeStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                case 'passport':
                                                                    $documentStatusCheck->where('passportStatus', 0);
                                                                    if ($data->passportStatus == 0) {
                                                                        $documentStatusCheck->addSelect(
                                                                            'passportStatus'
                                                                        );
                                                                    }
                                                                    break;

                                                                default:
                                                                    break;
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                        });
                                    });
                                });
                            }
                            return  $documentStatusCheck->get();
                        }
                    }
                }

    }

    public function verifyKYCInfo(Request $request)
    {
        //try{uest
        $data = KYC::where('id', $request->data_id)->first();

        $kycActivityLogs = new KycActivityLogs();
        if (isset($data)) {
            $kycActivityLogs->old_data_json = json_encode($data);
        } else {
            $kycActivityLogs->old_data_json = NULL;
        }
        $has_vehicle = 0;
        $product_id = 0;
        if ($data->customer_id != null) {
            $customer = Customer::where('id', $data->customer_id)->first(array('id', 'email', 'firstName', 'lastName', 'cellphone'));
            $policies = Policy::where('customer_id', $data->customer_id)->where('status', "!=","2")->get(array('id', 'agent_id', 'policyNumber', 'has_vehicle', 'product_id', 'customer_id'));
            foreach ($policies as $key => $policy) {
                if ($policy->has_vehicle == 1) {
                    $has_vehicle = 1;
                }
                 //dd($policy->has_vehicle);
            }
        }

        // date of birth save
        if (isset($request->dob)){
            $custData = CustomerProfile::where('customer_id',$data->customer_id)->first();
            $custData->dob = date("Y-m-d", strtotime($request->dob));;
            $custData->save();
        }

        if (isset($request->data_id)) {
            if ($request->remark) {
                $data->remark = $request->remark;
                if ($data->isDirty('remark')) {
                    $kycActivityLogs->description .= 'Remark has updated to ' . $data->remark;
                }
            } else {
                $data->remark = '--';
            }
            $docarray = array();
            $data->omangFrontStatus      = isset($request->omangFrontStatus) ? $request->omangFrontStatus : 0;
            if ($data->isDirty('omangFrontStatus')) {
                $kycActivityLogs->description .= 'Omang Front Status has updated to ' . $data->omangFrontStatus;
            }
            if($data->omangFrontStatus ==0){
            array_push($docarray," omang  Front");
            }
            $data->omang_approved_date   = ($data->omangFrontStatus == 1) ? date('Y-m-d') : null;
            $data->omangFrontRemark      = isset($request->omangFrontRemark) ? $request->omangFrontRemark : '';
            if ($data->isDirty('omangFrontRemark') && !isset($request->passportStatus)) {
                $kycActivityLogs->description .= 'Omang Front Remark has updated to ' . $data->omangFrontRemark;
            }
            $data->omangBackStatus       = isset($request->omangBackStatus) ? $request->omangBackStatus : 0;
            if ($data->isDirty('omangBackStatus')) {
                $kycActivityLogs->description .= 'Omang Back Status has updated to ' . $data->omangBackStatus;
            }
            if($data->omangBackStatus ==0 && !isset($request->passportStatus)){
                array_push($docarray," omang  Back");
                }
            $data->omangBackRemark       = isset($request->omangBackRemark) ? $request->omangBackRemark : '';
            if ($data->isDirty('omangBackRemark')) {
                $kycActivityLogs->description .= 'Omang Back Remark has updated to' . $data->omangBackRemark;
            }
            $data->passportStatus        = isset($request->passportStatus) ? $request->passportStatus : 0;
            if ($data->isDirty('passportStatus')) {
                $kycActivityLogs->description .= 'Passport Status has updated to ' . $data->passportStatus;
            }
            if($data->passportStatus ==0 &&  !isset($request->omangBackStatus)){
                array_push($docarray," Passport");
                }
            $data->passport_approved_date = ($data->passportStatus == 1) ? date('Y-m-d') : null;
            $data->passportRemark        = isset($request->passportRemark)  ? $request->passportRemark : '';
            if ($data->isDirty('passportRemark')) {
                $kycActivityLogs->description .= 'Passport Remark has updated to ' . $data->passportRemark;
            }
            $data->driving_licenseStatus = isset($request->driving_licenseStatus) ? $request->driving_licenseStatus : 0;
            if ($data->isDirty('driving_licenseStatus')) {
                $kycActivityLogs->description .= 'Driving License Status has updated to ' . $data->driving_licenseStatus;
            }
            if($data->driving_licenseStatus ==0 && $request->product_id == 3){
                array_push($docarray," Driving License");
                }
            $data->license_approved_date = ($data->driving_licenseStatus) ? date('Y-m-d') : null;

            $data->driving_licenseRemark = isset($request->driving_licenseRemark)  ? $request->driving_licenseRemark : '';
            if ($data->isDirty('driving_licenseRemark')) {
                $kycActivityLogs->description .= 'Driving License Remark has updated to ' . $data->driving_licenseRemark;
            }
            $data->proof_residenceStatus = isset($request->proof_residenceStatus) ? $request->proof_residenceStatus : 0;
            if ($data->isDirty('proof_residenceStatus')) {
                $kycActivityLogs->description .= 'Proof Residence Status has updated to ' . $data->proof_residenceStatus;
            }
            if($data->proof_residenceStatus ==0 && $request->product_id == 3){
                array_push($docarray," Proof Residence");
                }
            $data->proof_residenceRemark = isset($request->proof_residenceRemark) ? $request->proof_residenceRemark : '';
            if ($data->isDirty('proof_residenceRemark')) {
                $kycActivityLogs->description .= 'Proof Residence Remark has updated to ' . $data->proof_residenceRemark;
            }
            $data->proof_incomeStatus    = isset($request->proof_incomeStatus) ? $request->proof_incomeStatus : 0;
            if ($data->isDirty('proof_incomeStatus')) {
                $kycActivityLogs->description .= 'Proof Income Status has updated to ' . $data->proof_incomeStatus;
            }
            if($data->proof_incomeStatus ==0 && $request->product_id == 3){
                array_push($docarray," Proof Income");
                }
            $data->proof_incomeRemark    = isset($request->proof_incomeRemark) ? $request->proof_incomeRemark : '';
            if ($data->isDirty('proof_incomeRemark')) {
                $kycActivityLogs->description .= 'Proof Income Remark has updated to' . $data->proof_incomeRemark;
            }

            if (isset($request->date_of_omangExpiry)) {
                $data->omangExpiry = $request->date_of_omangExpiry;
                if ($data->isDirty('omangExpiry')) {
                    $kycActivityLogs->description .= 'Omang Expiry has updated to ' . $data->omangExpiry;
                }
            }

            if (isset($request->date_of_passportExpiry)) {
                $data->passportExpiry = $request->date_of_passportExpiry;
                if ($data->isDirty('passportExpiry')) {
                    $kycActivityLogs->description .= 'Passport Expiry has updated to ' . $data->passportExpiry;
                }
            }

            if (isset($request->date_of_drivingLicenseExpiry)) {
                $data->licenseExpiry = $request->date_of_drivingLicenseExpiry;
                if ($data->isDirty('licenseExpiry')) {
                    $kycActivityLogs->description .= 'Driving License Expiry has updated to ' . $data->licenseExpiry;
                }
            }

            // $data->compliance = $this->getComplianceStatus($request,$has_vehicle);
            $data->save();
            
            // Update banking document statuses in KYC table
            // Bank Statement File Status
            if (isset($request->bankStatementFileStatus)) {
                $data->bankStatementFileStatus = $request->bankStatementFileStatus;
                if ($data->isDirty('bankStatementFileStatus')) {
                    $kycActivityLogs->description .= 'Bank Statement File Status has updated to ' . $data->bankStatementFileStatus;
                }
            }
            
            // Bank Statement File Remark
            if (isset($request->bankStatementFileRemark)) {
                $data->bankStatementFileRemark = $request->bankStatementFileRemark;
                if ($data->isDirty('bankStatementFileRemark')) {
                    $kycActivityLogs->description .= 'Bank Statement File Remark has updated to ' . $data->bankStatementFileRemark;
                }
            }
            
            // Debit Authorization Form Status
            if (isset($request->debitAuthFileStatus)) {
                $data->debitAuthFileStatus = $request->debitAuthFileStatus;
                if ($data->isDirty('debitAuthFileStatus')) {
                    $kycActivityLogs->description .= 'Debit Authorization Form Status has updated to ' . $data->debitAuthFileStatus;
                }
            }
            
            // Debit Authorization Form Remark
            if (isset($request->debitAuthFileRemark)) {
                $data->debitAuthFileRemark = $request->debitAuthFileRemark;
                if ($data->isDirty('debitAuthFileRemark')) {
                    $kycActivityLogs->description .= 'Debit Authorization Form Remark has updated to ' . $data->debitAuthFileRemark;
                }
            }
            
            // dd($data);
            $complianceSts = $this->getKycComplianceStatus($data, $policies);
            // dd($complianceSts);
            $data->compliance = $complianceSts['compliance'];

            //kyc incentive to be added by event event type will be 2 for it
            $policies = Policy::where('customer_id', $data->customer_id)->latest('created_at')->first();
            $status = $complianceSts['compliance'];
            if($policies->agent_id != null){
                $incentivetype = 2;
                $amount = $policies->premium;
                event(new \Modules\Incentive\Events\AddIncentive($policies,$incentivetype,$status,$amount));
            }
            $type = 2;
            event(new \Modules\Cashback\Events\CustomerCashbackEvent($policies,$type,$status));

            $data->performed_by = auth()->user()->id;

            if (isset($data->customer_id)) {
                $kycActivityLogs->customer_id = $data->customer_id;
            } else {
                $kycActivityLogs->customer_id = NULL;
            }
            $kycActivityLogs->log_name = "Customer KYC Log";

            if (isset($data->status)) {
                if ($data->isDirty('status')) {
                    $kycActivityLogs->status = "Status has updated to " . $data->status;
                }
            } else {
                if ($data->isDirty('status')) {
                    $kycActivityLogs->status = "Status has updated to - ";
                }
            }

            if ($data->compliance == 2) {
                $kycActivityLogs->compliance = "No";
                if ($data->isDirty('compliance')) {
                    $kycActivityLogs->description .= 'Compliance has updated to ' . $data->compliance;
                }
            } elseif ($data->compliance == 1) {
                $kycActivityLogs->compliance = "Yes";
                if ($data->isDirty('compliance')) {
                    $kycActivityLogs->description .= 'Compliance has updated to ' . $data->compliance;
                }
            } else {
                $kycActivityLogs->compliance = "Pending";
                if ($data->isDirty('compliance')) {
                    $kycActivityLogs->description .= 'Compliance has updated to ' . $data->compliance;
                }
            }

            if (isset($data->reason)) {
                $kycActivityLogs->reason = $data->reason;
                if ($data->isDirty('reason')) {
                    $kycActivityLogs->description .= 'Reason has updated to ' . $data->reason;
                }
            } else {
                $kycActivityLogs->reason = NULL;
                if ($data->isDirty('reason')) {
                    $kycActivityLogs->description .= 'Reason has updated to - ';
                }
            }

            if (isset($data->performed_by)) {
                $kycActivityLogs->action_perfomed_by = $data->performed_by;
            } else {
                $kycActivityLogs->action_perfomed_by = NULL;
            }

            if (isset($data->updated_at)) {
                $kycActivityLogs->action_performed_at = $data->updated_at;
            } else {
                $kycActivityLogs->action_performed_at = NULL;
            }

            $kycActivityLogs->save();
            $data->save();
            // V8 parity (CustomerController.php:2627): suppress MIS-tier
            // KYC notifications when the customer also holds DOM/COM-tier
            // policies. Those products have their own review flow
            // (verifyKYCInfoForDomCom) and shouldn't receive the MIS
            // "kyc_update" email/SMS/WhatsApp on this path.
            $isDomComCustomer = Policy::where('customer_id', $data->customer_id)
                ->whereIn('product_id', \AlphaDirect\Support\KycDomComProducts::IDS)
                ->exists();
            if ($customer && $customer->email && $data->compliance == 2 && !$isDomComCustomer) {
                $email = new \stdClass();
                $email->user_id = $data->id;
                $email->hook = 'kyc_update';
                $email->customer_id = $customer->id;
                $email->attachment = null;
                $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
                $markdown = new MailTemplate($email);
                $html = $markdown->render('Mail.mailTemplate', ['data' => $email]);
                event(new \AlphaDirect\Events\SendMail($customer->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => $policy->policyNumber, 'hook' => $email->hook]));

                //$sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($email));
                $policies = Policy::where('customer_id', $data->customer_id)->latest('created_at')->get();
                    foreach ($policies as   $policy) {
                        $sentDocs = new sentPolicyDocumentLogs();
                        $sentDocs->policyNumber = $policy->policyNumber;
                        $sentDocs->email = $customer->email;
                        $sentDocs->sentBy = 'Agent';
                        $sentDocs->doc = 'KYC Update';
                        $sentDocs->save();
                    }

                    foreach ($policies as $key => $policy) {
                        if ($policy->agent_id != null) {
                            $agent = User::join('user_profile', 'user_profile.user_id', 'users.id')
                                ->where('users.id', $policy->agent_id)
                                ->first(array('users.id', 'users.firstName', 'users.firstName', 'user_profile.cellphone', 'users.email'));
                            if ($agent && $agent->cellphone) {
                                $sms = new SmsMessaging();
                                $send = $sms->smsAgentKYC(14, $agent->firstName, $customer->firstName . ' ' . $customer->lastName, $policy->policyNumber, $agent->cellphone);
                            }
                            if ($agent && $agent->email) {
                                $email = new \stdClass();
                                $email->user_id = $data->id;
                                $email->hook = 'kyc_update_agent';
                                $email->customer_id = $customer->id;
                                $email->attachment = null;
                                $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
                                $markdown = new MailTemplate($email);
                                $html = $markdown->render('Mail.mailTemplate', ['data' => $email]);
                                event(new \AlphaDirect\Events\SendMail($customer->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => $policy->policyNumber, 'hook' => $email->hook]));
                                //$sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($email));
                            }
                        }
                    }

            }
            $policies = Policy::where('customer_id', $data->customer_id)->latest('created_at')->first();

            // Same DOM/COM suppression — SMS + WhatsApp paths. V8 parity.
            if ($customer->cellphone && $data->compliance == 2 && !$isDomComCustomer){
                $sendSms = event(new \AlphaDirect\Events\SendSms('+267' . $customer->cellphone, 'Dumelang ' . $customer->firstName . ', Your KYC photos submitted on Alpha Direct are rejected.'));
                 //$sendSms = InfobipSms::send('+267' . $customer->cellphone, 'Dumelang ' . $customer->firstName . ', Your KYC photos submitted on Alpha Direct are rejected.');

                if(!empty($docarray)){
                $dataRejectPolicy =[
                    "type"=>"template",
                    "subType"=>"reject_policy_document",
                    "mobileNumber"=>'267'.$customer->cellphone ,
                    "firstName"=> $customer->firstName ,
                    "lastName"=>$customer->lastName,
                    "policyNumber"=>$policies->policyNumber,
                    "customer_id"=>$data->customer_id,
                    "docList"=>implode(", ",$docarray)
                    ];
                        Log::info(json_encode($dataRejectPolicy));

                 $WhatsAppController=  new WhatsAppController();
                 $WhatsAppController->sendMessage($dataRejectPolicy);
                }
            }

            if( env('APP_STATUS') === 'Production' ){
                $llmapi = new LlmApiCrontroller();
                $llmapi->kycStatus($policy->policyNumber);
            }

            if ($has_vehicle == 1 && $data->driving_license == null && $policies->product_id == 3)
                return Redirect::back()->with('info', 'Drivers license is mandatory for the customers holding policies with vehicle');
            elseif ($data->compliance == 1)
                return Redirect::back()->with('success', 'Information updated successfully, customer is KYC compliant');
            elseif ($data->compliance != 1)
                return Redirect::back()->with('info', 'Customer is not KYC compliant');
            if ($data->save())
                return Redirect::back()->with('success', 'Information updated successfully');
            else
                return Redirect::back()->with('error', 'Something went wrong');
        } else {
            return Redirect::back()->with('error', 'Missing data');
        }
        // }catch(\Exception $e){
        //     return Redirect::back()->with('error', $e->getMessage());
        // }
    }

    public function verifyKYCInfoForDomCom(Request $request)
    {
        //try{
        $data = KYC::where('id', $request->data_id)->first();
        $kycDomCom = CustomerKycDomCom::where('customer_kyc_id',  $request->data_id)->first();

        if (!isset($kycDomCom)) {
            $kycDomCom = new CustomerKycDomCom();
            $kycDomCom->customer_id = $data->customer_id;
            $kycDomCom->customer_kyc_id = $data->id;
        }

        // Initialize an array to hold both data sets
        $mergedData = [];

        // If KYC data exists, add it to the mergedData array
        if (isset($data)) {
            $mergedData['KYC'] = $data->toArray();
        }

        // If CustomerKycDomCom data exists, add it to the mergedData array
        if (isset($kycDomCom)) {
            $mergedData['CustomerKycDomCom'] = $kycDomCom->toArray();
        }

        $kycActivityLogs = new KycActivityLogs();
        if (!empty($mergedData)) {
            $kycActivityLogs->old_data_json = json_encode($mergedData);
        } else {
            $kycActivityLogs->old_data_json = NULL;
        }

        $has_vehicle = 0;
        $product_id = 0;
        if ($data->customer_id != null) {
            $customer = Customer::where('id', $data->customer_id)->first(array('id', 'email', 'firstName', 'lastName', 'cellphone'));
            $policies = Policy::where('customer_id', $data->customer_id)->where('status', "!=","2")->get(array('id', 'agent_id', 'policyNumber', 'has_vehicle', 'product_id', 'customer_id'));
            foreach ($policies as $key => $policy) {
                if ($policy->has_vehicle == 1) {
                    $has_vehicle = 1;
                }
                 //dd($policy->has_vehicle);
            }
        }

        // date of birth save
        if (isset($request->dob)){
            $custData = CustomerProfile::where('customer_id',$data->customer_id)->first();
            $custData->dob = date("Y-m-d", strtotime($request->dob));
            $custData->save();
        }

        if (isset($request->data_id)) {
            if ($request->remark) {
                $data->remark = $request->remark;
                if ($data->isDirty('remark')) {
                    $kycActivityLogs->description .= 'Remark has updated to ' . $data->remark;
                }
            } else {
                $data->remark = '--';
            }
            $docarray = array();
            $data->omangFrontStatus      = isset($request->omangFrontStatus) ? $request->omangFrontStatus : 0;
            if ($data->isDirty('omangFrontStatus')) {
                $kycActivityLogs->description .= 'Omang Front Status has updated to ' . $data->omangFrontStatus;
            }
            if($data->omangFrontStatus ==0){
            array_push($docarray," omang  Front");
            }
            $data->omang_approved_date   = ($data->omangFrontStatus == 1) ? date('Y-m-d') : null;
            $data->omangFrontRemark      = isset($request->omangFrontRemark) ? $request->omangFrontRemark : '';
            if ($data->isDirty('omangFrontRemark') && !isset($request->passportStatus)) {
                $kycActivityLogs->description .= 'Omang Front Remark has updated to ' . $data->omangFrontRemark;
            }
            $data->omangBackStatus       = isset($request->omangBackStatus) ? $request->omangBackStatus : 0;
            if ($data->isDirty('omangBackStatus')) {
                $kycActivityLogs->description .= 'Omang Back Status has updated to ' . $data->omangBackStatus;
            }
            if($data->omangBackStatus ==0 && !isset($request->passportStatus)){
                array_push($docarray," omang  Back");
                }
            $data->omangBackRemark       = isset($request->omangBackRemark) ? $request->omangBackRemark : '';
            if ($data->isDirty('omangBackRemark')) {
                $kycActivityLogs->description .= 'Omang Back Remark has updated to' . $data->omangBackRemark;
            }
            $data->passportStatus        = isset($request->passportStatus) ? $request->passportStatus : 0;
            if ($data->isDirty('passportStatus')) {
                $kycActivityLogs->description .= 'Passport Status has updated to ' . $data->passportStatus;
            }
            if($data->passportStatus ==0 &&  !isset($request->omangBackStatus)){
                array_push($docarray," Passport");
                }
            $data->passport_approved_date = ($data->passportStatus == 1) ? date('Y-m-d') : null;
            $data->passportRemark        = isset($request->passportRemark)  ? $request->passportRemark : '';
            if ($data->isDirty('passportRemark')) {
                $kycActivityLogs->description .= 'Passport Remark has updated to ' . $data->passportRemark;
            }
            $data->driving_licenseStatus = isset($request->driving_licenseStatus) ? $request->driving_licenseStatus : 0;
            if ($data->isDirty('driving_licenseStatus')) {
                $kycActivityLogs->description .= 'Driving License Status has updated to ' . $data->driving_licenseStatus;
            }
            if($data->driving_licenseStatus ==0 && $request->product_id == 3){
                array_push($docarray," Driving License");
                }
            $data->license_approved_date = ($data->driving_licenseStatus) ? date('Y-m-d') : null;

            $data->driving_licenseRemark = isset($request->driving_licenseRemark)  ? $request->driving_licenseRemark : '';
            if ($data->isDirty('driving_licenseRemark')) {
                $kycActivityLogs->description .= 'Driving License Remark has updated to ' . $data->driving_licenseRemark;
            }
            $data->proof_residenceStatus = isset($request->proof_residenceStatus) ? $request->proof_residenceStatus : 0;
            if ($data->isDirty('proof_residenceStatus')) {
                $kycActivityLogs->description .= 'Proof Residence Status has updated to ' . $data->proof_residenceStatus;
            }
            if($data->proof_residenceStatus ==0 && $request->product_id == 3){
                array_push($docarray," Proof Residence");
                }
            $data->proof_residenceRemark = isset($request->proof_residenceRemark) ? $request->proof_residenceRemark : '';
            if ($data->isDirty('proof_residenceRemark')) {
                $kycActivityLogs->description .= 'Proof Residence Remark has updated to ' . $data->proof_residenceRemark;
            }
            $data->proof_incomeStatus    = isset($request->proof_incomeStatus) ? $request->proof_incomeStatus : 0;
            if ($data->isDirty('proof_incomeStatus')) {
                $kycActivityLogs->description .= 'Proof Income Status has updated to ' . $data->proof_incomeStatus;
            }
            if($data->proof_incomeStatus ==0 && $request->product_id == 3){
                array_push($docarray," Proof Income");
                }
            $data->proof_incomeRemark    = isset($request->proof_incomeRemark) ? $request->proof_incomeRemark : '';
            if ($data->isDirty('proof_incomeRemark')) {
                $kycActivityLogs->description .= 'Proof Income Remark has updated to' . $data->proof_incomeRemark;
            }

            if (isset($request->date_of_omangExpiry)) {
                $data->omangExpiry = $request->date_of_omangExpiry;
                if ($data->isDirty('omangExpiry')) {
                    $kycActivityLogs->description .= 'Omang Expiry has updated to ' . $data->omangExpiry;
                }
            }

            if (isset($request->date_of_passportExpiry)) {
                $data->passportExpiry = $request->date_of_passportExpiry;
                if ($data->isDirty('passportExpiry')) {
                    $kycActivityLogs->description .= 'Passport Expiry has updated to ' . $data->passportExpiry;
                }
            }

            if (isset($request->date_of_drivingLicenseExpiry)) {
                $data->licenseExpiry = $request->date_of_drivingLicenseExpiry;
                if ($data->isDirty('licenseExpiry')) {
                    $kycActivityLogs->description .= 'Driving License Expiry has updated to ' . $data->licenseExpiry;
                }
            }

            if (isset($request->directorsIDExp)) {
                $kycDomCom->directorsIDExp = $request->directorsIDExp;
                if ($kycDomCom->isDirty('directorsIDExp')) {
                    $kycActivityLogs->description .= 'Directors ID Expiry has updated to ' . $kycDomCom->directorsIDExp;
                }
            }

            if (isset($request->directorsPassportExp)) {
                $kycDomCom->directorsPassportExp = $request->directorsPassportExp;
                if ($kycDomCom->isDirty('directorsPassportExp')) {
                    $kycActivityLogs->description .= 'Directors Passport Expiry has updated to ' . $kycDomCom->directorsPassportExp;
                }
            }

            if (isset($request->shareholdersIDExp)) {
                $kycDomCom->shareholdersIDExp = $request->shareholdersIDExp;
                if ($kycDomCom->isDirty('shareholdersIDExp')) {
                    $kycActivityLogs->description .= 'Shareholders ID Expiry has updated to ' . $kycDomCom->shareholdersIDExp;
                }
            }

            if (isset($request->shareholdersPassportExp)) {
                $kycDomCom->shareholdersPassportExp = $request->shareholdersPassportExp;
                if ($kycDomCom->isDirty('shareholdersPassportExp')) {
                    $kycActivityLogs->description .= 'Shareholders Passport Expiry has updated to ' . $kycDomCom->shareholdersPassportExp;
                }
            }

            if (isset($kycDomCom)) {
                // Separate status and remark fields
                $statusFields = array_filter($kycDomCom->toArray(), function ($key) {
                    return strpos($key, '_status') !== false; // Filter status fields
                }, ARRAY_FILTER_USE_KEY);

                $remarkFields = array_filter($kycDomCom->toArray(), function ($key) {
                    return strpos($key, '_remark') !== false; // Filter remark fields
                }, ARRAY_FILTER_USE_KEY);

                // Iterate over the dynamic status fields and check for updates
                foreach ($statusFields as $field => $value) {
                    $kycDomCom->$field = isset($request->$field) ? $request->$field : 0;

                    if ($kycDomCom->isDirty($field)) {
                        $kycActivityLogs->description .= ucfirst(str_replace('_', ' ', $field)) . ' has updated to ' . $kycDomCom->$field . '. ';
                    }

                    // Custom logic for product_id 7 or 8
                    if (($request->product_id == 7 || $request->product_id == 8) && $kycDomCom->$field == 0) {
                        array_push($docarray, ucfirst(str_replace('_status', '', str_replace('_', ' ', $field))));
                    }
                }

                // Iterate over the dynamic remark fields and check for updates
                foreach ($remarkFields as $field => $value) {
                    $kycDomCom->$field = isset($request->$field) ? $request->$field : '';

                    if ($kycDomCom->isDirty($field)) {
                        $kycActivityLogs->description .= ucfirst(str_replace('_', ' ', $field)) . ' has updated to ' . $kycDomCom->$field . '. ';
                    }
                }
            }

            // $data->compliance = $this->getComplianceStatus($request,$has_vehicle);
            $data->save();
            $kycDomCom->save();
            // dd($data);
            $complianceSts = $this->checkKycComplianceStatus($data->customer_id);
            // $complianceSts = $this->getKycComplianceStatus($data, $policies);
            // dd($complianceSts);
            // $data->compliance = $complianceSts['compliance'];
            $data->compliance = $complianceSts;

            //kyc incentive to be added by event event type will be 2 for it
            $policies = Policy::where('customer_id', $data->customer_id)->latest('created_at')->first();
            // $status = $complianceSts['compliance'];
            $status = $complianceSts;
            if($policies->agent_id != null){
                $incentivetype = 2;
                $amount = $policies->premium;
                event(new \Modules\Incentive\Events\AddIncentive($policies,$incentivetype,$status,$amount));
            }
            $type = 2;
            event(new \Modules\Cashback\Events\CustomerCashbackEvent($policies,$type,$status));

            $data->performed_by = auth()->user()->id;

            if (isset($data->customer_id)) {
                $kycActivityLogs->customer_id = $data->customer_id;
            } else {
                $kycActivityLogs->customer_id = NULL;
            }
            $kycActivityLogs->log_name = "Customer KYC Log";

            if (isset($data->status)) {
                if ($data->isDirty('status')) {
                    $kycActivityLogs->status = "Status has updated to " . $data->status;
                }
            } else {
                if ($data->isDirty('status')) {
                    $kycActivityLogs->status = "Status has updated to - ";
                }
            }

            if ($data->compliance == 2) {
                $kycActivityLogs->compliance = "No";
                if ($data->isDirty('compliance')) {
                    $kycActivityLogs->description .= 'Compliance has updated to ' . $data->compliance;
                }
            } elseif ($data->compliance == 1) {
                $kycActivityLogs->compliance = "Yes";
                if ($data->isDirty('compliance')) {
                    $kycActivityLogs->description .= 'Compliance has updated to ' . $data->compliance;
                }
            } else {
                $kycActivityLogs->compliance = "Pending";
                if ($data->isDirty('compliance')) {
                    $kycActivityLogs->description .= 'Compliance has updated to ' . $data->compliance;
                }
            }

            if (isset($data->reason)) {
                $kycActivityLogs->reason = $data->reason;
                if ($data->isDirty('reason')) {
                    $kycActivityLogs->description .= 'Reason has updated to ' . $data->reason;
                }
            } else {
                $kycActivityLogs->reason = NULL;
                if ($data->isDirty('reason')) {
                    $kycActivityLogs->description .= 'Reason has updated to - ';
                }
            }

            if (isset($data->performed_by)) {
                $kycActivityLogs->action_perfomed_by = $data->performed_by;
            } else {
                $kycActivityLogs->action_perfomed_by = NULL;
            }

            if (isset($data->updated_at)) {
                $kycActivityLogs->action_performed_at = $data->updated_at;
            } else {
                $kycActivityLogs->action_performed_at = NULL;
            }

            $kycDomCom->save();
            $kycActivityLogs->save();
            $data->save();
            // if ($customer && $customer->email && $data->compliance == 2) {
            //     $email = new \stdClass();
            //     $email->user_id = $data->id;
            //     $email->hook = 'kyc_update';
            //     $email->customer_id = $customer->id;
            //     $email->attachment = null;
            //     $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
            //     $markdown = new MailTemplate($email);
            //     $html = $markdown->render('Mail.mailTemplate', ['data' => $email]);
            //     event(new \AlphaDirect\Events\SendMail($customer->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => $policy->policyNumber, 'hook' => $email->hook]));

            //     //$sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($email));
            //     $policies = Policy::where('customer_id', $data->customer_id)->latest('created_at')->get();
            //         foreach ($policies as   $policy) {
            //             $sentDocs = new sentPolicyDocumentLogs();
            //             $sentDocs->policyNumber = $policy->policyNumber;
            //             $sentDocs->email = $customer->email;
            //             $sentDocs->sentBy = 'Agent';
            //             $sentDocs->doc = 'KYC Update';
            //             $sentDocs->save();
            //         }

            //         foreach ($policies as $key => $policy) {
            //             if ($policy->agent_id != null) {
            //                 $agent = User::join('user_profile', 'user_profile.user_id', 'users.id')
            //                     ->where('users.id', $policy->agent_id)
            //                     ->first(array('users.id', 'users.firstName', 'users.firstName', 'user_profile.cellphone', 'users.email'));
            //                 if ($agent && $agent->cellphone) {
            //                     $sms = new SmsMessaging();
            //                     $send = $sms->smsAgentKYC(14, $agent->firstName, $customer->firstName . ' ' . $customer->lastName, $policy->policyNumber, $agent->cellphone);
            //                 }
            //                 if ($agent && $agent->email) {
            //                     $email = new \stdClass();
            //                     $email->user_id = $data->id;
            //                     $email->hook = 'kyc_update_agent';
            //                     $email->customer_id = $customer->id;
            //                     $email->attachment = null;
            //                     $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
            //                     $markdown = new MailTemplate($email);
            //                     $html = $markdown->render('Mail.mailTemplate', ['data' => $email]);
            //                     event(new \AlphaDirect\Events\SendMail($customer->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => $policy->policyNumber, 'hook' => $email->hook]));
            //                     //$sent = \Illuminate\Support\Facades\Mail::to($customer->email)->send(new MailTemplate($email));
            //                 }
            //             }
            //         }

            // }
            $policies = Policy::where('customer_id', $data->customer_id)->latest('created_at')->first();

            // if ($customer->cellphone && $data->compliance == 2){
            //     $sendSms = event(new \AlphaDirect\Events\SendSms('+267' . $customer->cellphone, 'Dumelang ' . $customer->firstName . ', Your KYC photos submitted on Alpha Direct are rejected.'));
            //      //$sendSms = InfobipSms::send('+267' . $customer->cellphone, 'Dumelang ' . $customer->firstName . ', Your KYC photos submitted on Alpha Direct are rejected.');

            //     if(!empty($docarray)){
            //     $dataRejectPolicy =[
            //         "type"=>"template",
            //         "subType"=>"reject_policy_document",
            //         "mobileNumber"=>'267'.$customer->cellphone ,
            //         "firstName"=> $customer->firstName ,
            //         "lastName"=>$customer->lastName,
            //         "policyNumber"=>$policies->policyNumber,
            //         "customer_id"=>$data->customer_id,
            //         "docList"=>implode(", ",$docarray)
            //         ];
            //             Log::info(json_encode($dataRejectPolicy));

            //      $WhatsAppController=  new WhatsAppController();
            //      $WhatsAppController->sendMessage($dataRejectPolicy);
            //     }
            // }
            if ($has_vehicle == 1 && $data->driving_license == null && $policies->product_id == 3)
                return Redirect::back()->with('info', 'Drivers license is mandatory for the customers holding policies with vehicle');
            elseif ($data->compliance == 1)
                return Redirect::back()->with('success', 'Information updated successfully, customer is KYC compliant');
            elseif ($data->compliance != 1)
                return Redirect::back()->with('info', 'Customer is not KYC compliant');
            if ($data->save())
                return Redirect::back()->with('success', 'Information updated successfully');
            else
                return Redirect::back()->with('error', 'Something went wrong');
        } else {
            return Redirect::back()->with('error', 'Missing data');
        }
        // }catch(\Exception $e){
        //     return Redirect::back()->with('error', $e->getMessage());
        // }
    }

    // public function checkKycComplianceStatus($customerId)
    // {
    //     // Status fields coming from the customer_kyc_dom_com table
    //     $statusFields = [
    //         'kyc_form_status',
    //         'data_protection_form_status',
    //         'certificate_of_incorporation_status',
    //         'extract_controllers_status',
    //         'resolution_status',
    //         'proof_business_address_status',
    //         'proof_residential_address_status',
    //     ];

    //     // Fetching records from both tables
    //     $kyc = KYC::where('customer_id', $customerId)->first();
    //     $kycDomCom = CustomerKycDomCom::where('customer_id', $customerId)->first();

    //     // If $kycDomCom is not an object, set compliance to false
    //     if (!is_object($kycDomCom)) {
    //         return $this->updateKycCompliance($kyc, 2); // Non-compliant
    //     }

    //     // Initialize compliance flag
    //     $compliant = true;

    //     // Check status for all fields in the customer_kyc_dom_com table
    //     foreach ($statusFields as $field) {
    //         if (($kycDomCom->$field ?? 0) != 1) {
    //             $compliant = false;
    //             break;
    //         }
    //     }

    //     // Check directors and shareholders compliance
    //     $directorsCompliant = $this->checkEntityCompliance($kycDomCom, 'directors');
    //     $shareholdersCompliant = $this->checkEntityCompliance($kycDomCom, 'shareholders');

    //     // If either directors or shareholders compliance fails, set overall compliance to false
    //     if (!$directorsCompliant || !$shareholdersCompliant) {
    //         $compliant = false;
    //     }

    //     // Update compliance status in KYC
    //     return $this->updateKycCompliance($kyc, $compliant ? 1 : 2);
    // }

    // public function checkKycComplianceStatus($customerId)
    // {
    //     // Define human-readable names for each status field
    //     $statusFieldNames = [
    //         'kyc_form_status' => 'KYC Form',
    //         'data_protection_form_status' => 'Data Protection Form',
    //         'certificate_of_incorporation_status' => 'Certificate of Incorporation',
    //         'extract_controllers_status' => 'Extract Controllers',
    //         'resolution_status' => 'Resolution',
    //         'proof_business_address_status' => 'Proof of Business Address',
    //         'proof_residential_address_status' => 'Proof of Residential Address',
    //     ];

    //     $kyc = KYC::where('customer_id', $customerId)->first();
    //     $kycDomCom = CustomerKycDomCom::where('customer_id', $customerId)->first();

    //     if (!is_object($kycDomCom)) {
    //         return $this->updateKycCompliance($kyc, 2, 'Missing customer KYC DOM/COM data'); // Non-compliant
    //     }

    //     $compliant = true;
    //     $failureReasons = []; // Initialize an array to hold failure reasons

    //     // Check status for all fields in customer_kyc_dom_com
    //     foreach ($statusFieldNames as $field => $name) {
    //         if (($kycDomCom->$field ?? 0) != 1) {
    //             $compliant = false;
    //             $failureReasons[] = "$name compliance check failed"; // Use the human-readable name
    //         }
    //     }

    //     // Check directors compliance
    //     $directorsCompliant = $this->checkEntityCompliance($kycDomCom, 'directors');
    //     if (!$directorsCompliant) {
    //         $compliant = false;
    //         $failureReasons[] = 'Directors compliance check failed'; // Add reason to the array
    //     }

    //     // Check shareholders compliance
    //     $shareholdersCompliant = $this->checkEntityCompliance($kycDomCom, 'shareholders');
    //     if (!$shareholdersCompliant) {
    //         $compliant = false;
    //         $failureReasons[] = 'Shareholders compliance check failed'; // Add reason to the array
    //     }

    //     // Prepend the message to the failure reasons
    //     $reason = null;
    //     if (!$compliant && !empty($failureReasons)) {
    //         $reason = 'Your KYC document(s) is unapproved due to the following reason(s): <br>' . implode('; ', $failureReasons);
    //     }

    //     // Update compliance status in KYC, passing all failure reasons if non-compliant
    //     return $this->updateKycCompliance($kyc, $compliant ? 1 : 2, $reason);
    // }

    public function checkKycComplianceStatus($customerId)
    {
        // Fetch product_id from the policies table grouped by product_id
        $policies = Policy::where('customer_id', $customerId)->groupBy('product_id')->get();

        if ($policies->isEmpty()) {
            return $this->updateKycCompliance(null, 2, 'No policies found for this customer');
        }

        $kyc = KYC::where('customer_id', $customerId)->first();
        $kycDomCom = CustomerKycDomCom::where('customer_id', $customerId)->first();

        if (!is_object($kycDomCom)) {
            return $this->updateKycCompliance($kyc, 2, 'Missing customer KYC DOM/COM data'); // Non-compliant
        }

        $overallCompliant = true; // To keep track of overall compliance across all products
        $allFailureReasons = []; // To hold reasons for non-compliance for all product_ids

        foreach ($policies as $policy) {
            $product_id = $policy->product_id;
            $compliant = true;
            $failureReasons = []; // Initialize an array to hold failure reasons for this product_id

            if ($product_id == 8) {
                // Product 8 specific documents from KYC table
                $kycDocumentFields = [
                    // 'omangFrontStatus',
                    // 'omangBackStatus',
                    'proof_residenceStatus' => 'Proof of residence',
                    'proof_incomeStatus'  => 'Proof of income',
                    // 'passportStatus'
                ];

                foreach ($kycDocumentFields as $field => $name) {
                    if (($kyc->$field ?? 0) != 1) {
                        $compliant = false;
                        $failureReasons[] = "$name compliance check failed";
                    }
                }

                // Additional documents from customer_kyc_dom_com table for product_id 8
                $domComFields = [
                    'kyc_form_status' => 'KYC Form',
                    'data_protection_form_status' => 'Data Protection Form',
                ];

                foreach ($domComFields as $field => $name) {
                    if (($kycDomCom->$field ?? 0) != 1) {
                        $compliant = false;
                        $failureReasons[] = "$name compliance check failed";
                    }
                }

                // Check omangFrontStatus, omangBackStatus, and passportStatus
                $omangFrontApproved = ($kyc->omangFrontStatus ?? 0) == 1;
                $omangBackApproved = ($kyc->omangBackStatus ?? 0) == 1;
                $passportApproved = ($kyc->passportStatus ?? 0) == 1;

                $getcompliantdata = ($omangFrontApproved && $omangBackApproved) || $passportApproved;
                if (!$getcompliantdata) {
                    $compliant = false;
                    // Check compliance for Omang and Passport
                    if (!$omangFrontApproved && !$omangBackApproved && !$passportApproved) {
                        $compliant = false; // Mark as non-compliant if any are unapproved

                        // Add specific failure reasons for unapproved documents
                        if (!$omangFrontApproved) {
                            $failureReasons[] = 'Omang Front compliance check failed';
                        }
                        if (!$omangBackApproved) {
                            $failureReasons[] = 'Omang Back compliance check failed';
                        }
                        if (!$passportApproved) {
                            $failureReasons[] = 'Passport compliance check failed';
                        }
                    }
                }

            } elseif ($product_id == 7) {
                // Product 7 specific documents from customer_kyc_dom_com table
                $statusFieldNames = [
                    'kyc_form_status' => 'KYC Form',
                    'data_protection_form_status' => 'Data Protection Form',
                    'certificate_of_incorporation_status' => 'Certificate of Incorporation',
                    'extract_controllers_status' => 'Extract Controllers',
                    'resolution_status' => 'Resolution',
                    'proof_business_address_status' => 'Proof of Business Address',
                    'proof_residential_address_status' => 'Proof of Residential Address',
                ];

                foreach ($statusFieldNames as $field => $name) {
                    if (($kycDomCom->$field ?? 0) != 1) {
                        $compliant = false;
                        $failureReasons[] = "$name compliance check failed";
                    }
                }

                // Directors and Shareholders compliance for product 7
                $directorsCompliant = $this->checkEntityCompliance($kycDomCom, 'directors');
                if (!$directorsCompliant) {
                    $compliant = false;
                    $failureReasons[] = 'Directors compliance check failed';
                }

                $shareholdersCompliant = $this->checkEntityCompliance($kycDomCom, 'shareholders');
                if (!$shareholdersCompliant) {
                    $compliant = false;
                    $failureReasons[] = 'Shareholders compliance check failed';
                }
            }

            // Combine failure reasons for this product_id
            if (!$compliant && !empty($failureReasons)) {
                $reason = "Compliance failed for product_id $product_id due to: <br>" . implode('; ', $failureReasons);
                $allFailureReasons[] = $reason;
                $overallCompliant = false;
            }
        }

        // Prepend the message to the failure reasons for all products
        $reason = null;
        if (!$overallCompliant && !empty($allFailureReasons)) {
            $reason = 'Your KYC document(s) are unapproved due to the following reason(s): <br>' . implode('<br>', $allFailureReasons);
        }

        // Update compliance status in KYC, passing all failure reasons if non-compliant
        return $this->updateKycCompliance($kyc, $overallCompliant ? 1 : 2, $reason);
    }



    private function checkEntityCompliance($kycDomCom, $type)
    {
        $fields = $type === 'directors' ? [
            'directors_id_front_status',
            'directors_id_back_status',
            'directors_passport_status',
        ] : [
            'shareholders_id_front_status',
            'shareholders_id_back_status',
            'shareholders_passport_status',
        ];

        // Check compliance for directors
        if ($type === 'directors') {
            $idFrontApproved = ($kycDomCom->{$fields[0]} ?? 0) == 1;
            $idBackApproved = ($kycDomCom->{$fields[1]} ?? 0) == 1;
            $passportApproved = ($kycDomCom->{$fields[2]} ?? 0) == 1;

            // Directors compliant if either both IDs are approved or the passport is approved
            return ($idFrontApproved && $idBackApproved) || $passportApproved;
        }

        // Check compliance for shareholders
        if ($type === 'shareholders') {
            $idFrontApproved = ($kycDomCom->{$fields[0]} ?? 0) == 1;
            $idBackApproved = ($kycDomCom->{$fields[1]} ?? 0) == 1;
            $passportApproved = ($kycDomCom->{$fields[2]} ?? 0) == 1;

            // Shareholders compliant if either both IDs are approved or the passport is approved
            return ($idFrontApproved && $idBackApproved) || $passportApproved;
        }

        return false; // Default return if none match
    }

    private function updateKycCompliance($kyc, $complianceStatus, $reason = null)
    {
        if (is_object($kyc)) {
            $kyc->compliance = $complianceStatus;

            // Handle compliance (approved) case
            if ($complianceStatus == 1) { // 1 means compliance is approved
                $kyc->status = 'Approve';
                $kyc->approved_date = Carbon::now();
            } else { // Non-compliance case
                $kyc->status = 'rejected';
                $kyc->approved_date = Carbon::now();
            }

            // Store the reason (combined failure reasons)
            $kyc->reason = $reason;

            $kyc->save();
        }

        return $complianceStatus;
    }

    public function getComplianceStatus(Request $request, $has_vehicle)
    {
        try {
            if ($has_vehicle == 1) {
                if (((isset($request->omangFrontStatus) && $request->omangFrontStatus == 1 && isset($request->omangBackStatus) && $request->omangBackStatus == 1) || isset($request->passportStatus) && $request->passportStatus == 1)
                    && isset($request->driving_licenseStatus) && $request->driving_licenseStatus == 1
                    && isset($request->proof_residenceStatus) && $request->proof_residenceStatus == 1
                    && isset($request->proof_incomeStatus) && $request->proof_incomeStatus == 1
                ) {
                    return 1; // 1- compliant , 2 - non-compliant
                } else {
                    return 2;
                }
            } else {
                if (((isset($request->omangFrontStatus) && $request->omangFrontStatus == 1 && isset($request->omangBackStatus) && $request->omangBackStatus == 1) || isset($request->passportStatus) && $request->passportStatus == 1)
                    && isset($request->proof_residenceStatus) && $request->proof_residenceStatus == 1
                    && isset($request->proof_incomeStatus) && $request->proof_incomeStatus == 1
                ) {
                    return 1; // 1- compliant , 2 - non-compliant
                } else {
                    return 2;
                }
            }
        } catch (\Exception $e) {
            return 2;
        }
    }

    public function updateEmailInfo(Request $request)
    {
        try {
            $message_id = $request["event-data"]["message"]["headers"]['message-id'];
            $to_email = $request["event-data"]["message"]["headers"]['to'];
            $status = $request["event-data"]['event'];
            $log = new \AlphaDirect\Models\SMSEmailLogs();
            $log->type = "Email";
            $log->content = json_encode($request->all());
            $log->message_id = $message_id;
            $log->status = strtoupper($status);
            $log->to_email = $to_email;
            $log->save();
           // \Log::info("Result After sending mail" . json_encode($request->all()));
            return response()->json(['success' => 1], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => 0], 401);
        }
    }

    public function updateSMSInfo(Request $request)
    {
        $data =  json_encode($request->all());
       // \Log::info("Result After sending SMS" . $data);
        return true;
    }

    public function export()
    {
        return Excel::download(new CustomerExport, 'Customers.csv');
    }

    public function checkCustomerQuotes($customerID)
    {
        try {
            $checkCount = MotorComprehensiveQuotes::where('customer_id', $customerID)
                ->where('status', 2)
                ->count();
            return $checkCount;
        } catch (\Exception $exception) {
            return 1;
        }
    }

    public function getmatiWebhook()
    {
        $identityStatus = request()->get('identityStatus');
        if(isset($identityStatus) && $identityStatus == "rejected" ){
            Log::info('rejected');
            return response()->json(['error' => 'rejected'], 422);
        }
        $mati = new CustomerMati();
        $resource = request()->get('resource');
        if ($resource != NULL)
            $resourceArray = explode('/', $resource);
        $mati->verification_id = $resourceArray[5];

        $verificationId = $resourceArray[5];

        $matiDashboardUrl = request()->get('matiDashboardUrl');
        $identityId = '';
        if ($matiDashboardUrl != NULL) {
            $matiDashboardUrlArray = explode('/', $matiDashboardUrl);
            $mati->identity_id = $matiDashboardUrlArray[4];
            $identityId = $matiDashboardUrlArray[4];
        }

        $step = request()->get('step');
        if ($step != NULL) {
            $mati->step_id = $step['id'];
        }

        $mati->event_name = request()->get('eventName');
        $mati->status = request()->get('status');
        $mati->header = json_encode(request()->header());
        $mati->request = json_encode(request()->all());

        //$mati->event_name = isset(request()->get('eventName')) ? request()->get('eventName') : NULL;
        //$mati->status = isset(request()->get('status')) ? request()->get('status') : NULL;

        // \DB::table('customer_mati')->insert([
        // 	'header'=>json_encode(request()->header()),
        // 	'request'=>json_encode(request()->all())
        // ]);

        $storeRawData = new CustomerMatiWebhook();
        $storeRawData->verification_id = $mati->verification_id;
        $storeRawData->identity_id = $mati->identity_id;
        $storeRawData->event_name = request()->get('eventName');
        $storeRawData->status = request()->get('status');
        $storeRawData->header = json_encode(request()->header());
        $storeRawData->request = json_encode(request()->all());
        $storeRawData->save();

        $curlToken = curl_init();

        curl_setopt_array($curlToken, array(
            CURLOPT_URL => "https://api.getmati.com/oauth",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Basic NjExMjZjZmIzODNmZjgwMDFiMjdhNGJmOlBGN1VUWFZZMkdOUkZRQUs1QUE0VDRaWDFOS0VVSDgy",
                "Content-Type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curlToken);
        $fetchToken = json_decode($response, true);
        curl_close($curlToken);

        if (isset($fetchToken['access_token']) && $fetchToken['access_token'] != null)
            $token = $fetchToken['access_token'];
        else
            return null;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            // CURLOPT_URL => "https://api.getmati.com/v2/verifications/6170122bbeb191001b266e9d",
            CURLOPT_URL => 'https://api.getmati.com/v2/verifications/' . $verificationId, //613f3b1475dc16001b45d7e4',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);
        $data = json_decode($response, true);
        curl_close($curl);

        $mati->response = json_encode($data);

        $storeRawData->response = $mati->response;
        $storeRawData->save();
        // dd($data);

        foreach ($data['documents'] as $key => $value) {
            if ($value['type'] == 'passport') {
                foreach ($value['photos'] as $key => $photo) {
                    if (isset($photo)) {
                        $image = Image::make($photo);
                        $image->encode('jpg');
                        $s3 = Storage::disk('s3');
                        $filePath = 'Mati/' . 'KYC' . '/' . $verificationId . '/passport';
                        $s3->put($filePath, $image->__toString(), 'public');
                        // $file = $photo;
                        // // $name = $file->getClientOriginalName();
                        // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/passport';
                        // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $mati->passport = $filePath;
                    }
                }
            }

            if ($value['type'] == 'driving-license') {
                foreach ($value['photos'] as $key => $photo) {
                    if (isset($photo)) {
                        $image = Image::make($photo);
                        $image->encode('jpg');
                        $s3 = Storage::disk('s3');
                        $filePath = 'Mati/' . 'KYC' . '/' . $verificationId . '/driving-license';
                        $s3->put($filePath, $image->__toString(), 'public');
                        // $file = $photo;
                        // // $name = $file->getClientOriginalName();
                        // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/driving-license';
                        // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $mati->driving_license = $filePath;
                    }
                }
            }


            if ($value['type'] == 'national-id') {
                foreach ($value['photos'] as $key => $photo) {
                    if (isset($photo)) {
                        if ($key == 0) {
                            $image = Image::make($photo);
                            $image->encode('jpg');
                            $s3 = Storage::disk('s3');
                            $filePath = 'Mati/' . 'KYC' . '/' . $verificationId . '/national-id';
                            $s3->put($filePath, $image->__toString(), 'public');
                            // $file = $photo;
                            // // $name = $file->getClientOriginalName();
                            // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/national-id';
                            // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $mati->omang = $filePath;
                        }

                        if ($key == 1) {
                            $image = Image::make($photo);
                            $image->encode('jpg');
                            $s3 = Storage::disk('s3');
                            $filePath = 'Mati/' . 'KYC' . '/' . $verificationId . '/national-id-back';
                            $s3->put($filePath, $image->__toString(), 'public');
                            // $file = $photo;
                            // // $name = $file->getClientOriginalName();
                            // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/national-id-back';
                            // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $mati->omangBack = $filePath;
                        }
                    }
                }
            }


            if ($value['type'] == 'proof-of-residency') {
                foreach ($value['photos'] as $key => $photo) {
                    if (isset($photo)) {
                        $image = Image::make($photo);
                        $image->encode('jpg');
                        $s3 = Storage::disk('s3');
                        $filePath = 'Mati/' . 'KYC' . '/' . $verificationId . '/proof-of-residency';
                        $s3->put($filePath, $image->__toString(), 'public');
                        // $file = $photo;
                        // // $name = $file->getClientOriginalName();
                        // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/proof-of-residency';
                        // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                        $mati->proof_residence = $filePath;
                    }
                }
            }
        }


        foreach ($data['steps'] as $key => $step) {
            if ($step['id'] == 'selfie') {
                if (isset($step['data']['selfiePhotoUrl'])) {
                    $image = Image::make($step['data']['selfiePhotoUrl']);
                    $image->encode('jpg');
                    $s3 = Storage::disk('s3');
                    $filePath = 'Mati/' . 'KYC' . '/' . $verificationId . '/selfie';
                    $s3->put($filePath, $image->__toString(), 'public');
                    // $file = $step['data']['selfiePhotoUrl'];
                    // // $name = $file->getClientOriginalName();
                    // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/selfie';
                    // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                    $mati->selfie = $filePath;
                }
            }
        }

        $mati->save();
        // dd($mati);
        if (isset($data['metadata']) && isset($data['metadata']['user_id'])) {
            $customer = Customer::where('id', $data['metadata']['user_id'])->first();
            if (isset($customer)) {
                $customer->mati_identity = $identityId;
                $customer->save();
            }
        } else {
            $customer = NULL;
        }

        $customer = Customer::where('mati_identity', $identityId)->first();

        if ($customer != NULL)
            $customerKyc = KYC::where('customer_id', $customer->id)->first();
        else
            $customerKyc = NULL;



        if ($customer != NULL && $customerKyc != NULL && isset($customer->mati_identity)) {
            $customerMati = CustomerMati::where('identity_id', $customer->mati_identity)->orderBy('id', 'desc')->first();

            if (isset($customerMati)) {
                if (isset($customerMati->passport)) {
                    $customerKyc->passport = $customerMati->passport;
                    $customerKyc->passportStatus = null;
                }

                if (isset($customerMati->driving_license)) {
                    $customerKyc->driving_license = $customerMati->driving_license;
                    $customerKyc->driving_licenseStatus = null;

                }

                if (isset($customerMati->omang)) {
                    $customerKyc->omang = $customerMati->omang;
                    $customerKyc->omangFrontStatus = null;
                }

                if (isset($customerMati->omangBack)) {
                    $customerKyc->omangBack = $customerMati->omangBack;
                    $customerKyc->omangBackStatus = null;
                }

                if (isset($customerMati->proof_residence)) {
                    $customerKyc->proof_residence = $customerMati->proof_residence;
                    $customerKyc->proof_residenceStatus = null;
                }

                // Recheck
               // if ($customerKyc->status == 'Unapprove') {
                    if(( $customerMati->passport || $customerMati->driving_license || $customerMati->omang || $customerMati->omangBack || $customerMati->proof_residence) != null){
                        $customerKyc->status = 'Recheck';
                        $customerKyc->compliance = 0;
                        $customerKyc->performed_by = null;
                    }
               // }

                $saved = $customerKyc->save();
            }
        }

        return response()->json(['success' => 'success'], 200);
    }

    public function fetchMatiData($identity_id)
    {
        $mati = CustomerMati::where('identity_id', $identity_id)->first();
        if (isset($mati)) {
            $curlToken = curl_init();

            curl_setopt_array($curlToken, array(
                CURLOPT_URL => "https://api.getmati.com/oauth",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic NjExMjZjZmIzODNmZjgwMDFiMjdhNGJmOlBGN1VUWFZZMkdOUkZRQUs1QUE0VDRaWDFOS0VVSDgy",
                    "Content-Type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curlToken);
            $fetchToken = json_decode($response, true);
            curl_close($curlToken);

            if ($fetchToken['access_token'])
                $token = $fetchToken['access_token'];
            else
                return null;


            // dd($token);
            if (isset($mati->identity_id) && isset($mati->verification_id)) {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    // CURLOPT_URL => "https://api.getmati.com/v2/verifications/6170122bbeb191001b266e9d",
                    CURLOPT_URL => 'https://api.getmati.com/v2/verifications/' . $mati->verification_id, //613f3b1475dc16001b45d7e4',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response, true);
                curl_close($curl);

                $mati->response = json_encode($data);
                // dd($data);

                foreach ($data['documents'] as $key => $value) {
                    if ($value['type'] == 'passport') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                $image = Image::make($photo);
                                $image->encode('jpg');
                                $s3 = Storage::disk('s3');
                                $filePath = 'Mati/' . 'KYC' . '/' . $mati->verification_id . '/passport';
                                $s3->put($filePath, $image->__toString(), 'public');
                                // $file = $photo;
                                // // $name = $file->getClientOriginalName();
                                // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/passport';
                                // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $mati->passport = $filePath;
                            }
                        }
                    }

                    if ($value['type'] == 'driving-license') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                $image = Image::make($photo);
                                $image->encode('jpg');
                                $s3 = Storage::disk('s3');
                                $filePath = 'Mati/' . 'KYC' . '/' . $mati->verification_id . '/driving-license';
                                $s3->put($filePath, $image->__toString(), 'public');
                                // $file = $photo;
                                // // $name = $file->getClientOriginalName();
                                // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/driving-license';
                                // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $mati->driving_license = $filePath;
                            }
                        }
                    }


                    if ($value['type'] == 'national-id') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                if ($key == 0) {
                                    $image = Image::make($photo);
                                    $image->encode('jpg');
                                    $s3 = Storage::disk('s3');
                                    $filePath = 'Mati/' . 'KYC' . '/' . $mati->verification_id . '/national-id';
                                    $s3->put($filePath, $image->__toString(), 'public');
                                    // $file = $photo;
                                    // // $name = $file->getClientOriginalName();
                                    // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/national-id';
                                    // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $mati->omang = $filePath;
                                }

                                if ($key == 1) {
                                    $image = Image::make($photo);
                                    $image->encode('jpg');
                                    $s3 = Storage::disk('s3');
                                    $filePath = 'Mati/' . 'KYC' . '/' . $mati->verification_id . '/national-id-back';
                                    $s3->put($filePath, $image->__toString(), 'public');
                                    // $file = $photo;
                                    // // $name = $file->getClientOriginalName();
                                    // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/national-id-back';
                                    // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                    $mati->omangBack = $filePath;
                                }
                            }
                        }
                    }


                    if ($value['type'] == 'proof-of-residency') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                $image = Image::make($photo);
                                $image->encode('jpg');
                                $s3 = Storage::disk('s3');
                                $filePath = 'Mati/' . 'KYC' . '/' . $mati->verification_id . '/proof-of-residency';
                                $s3->put($filePath, $image->__toString(), 'public');
                                // $file = $photo;
                                // // $name = $file->getClientOriginalName();
                                // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/proof-of-residency';
                                // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                                $mati->proof_residence = $filePath;
                            }
                        }
                    }
                }


                foreach ($data['steps'] as $key => $step) {
                    if ($step['id'] == 'selfie') {
                        if (isset($step['data']['selfiePhotoUrl'])) {
                            $image = Image::make($step['data']['selfiePhotoUrl']);
                            $image->encode('jpg');
                            $s3 = Storage::disk('s3');
                            $filePath = 'Mati/' . 'KYC' . '/' . $mati->verification_id . '/selfie';
                            $s3->put($filePath, $image->__toString(), 'public');
                            // $file = $step['data']['selfiePhotoUrl'];
                            // // $name = $file->getClientOriginalName();
                            // $filePath = 'Mati/' . 'KYC' . '/' . $identityId . '/selfie';
                            // Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                            $mati->selfie = $filePath;
                        }
                    }
                }

                $mati->save();
                // dd($mati);
                if (isset($data['metadata']) && isset($data['metadata']['user_id'])) {
                    $customer = Customer::where('id', $data['metadata']['user_id'])->first();
                    if (isset($customer)) {
                        $customer->mati_identity = $mati->identity_id;
                        $customer->save();
                    }
                } else {
                    $customer = NULL;
                }

                $customer = Customer::where('mati_identity', $mati->identity_id)->first();

                if ($customer != NULL)
                    $customerKyc = KYC::where('customer_id', $customer->id)->first();
                else
                    $customerKyc = NULL;



                if ($customer != NULL && $customerKyc != NULL && isset($customer->mati_identity)) {
                    $customerMati = CustomerMati::where('identity_id', $customer->mati_identity)->orderBy('id', 'desc')->first();

                    if (isset($customerMati)) {
                        if (isset($customerMati->passport)) {
                            $customerKyc->passport = $customerMati->passport;
                        }

                        if (isset($customerMati->driving_license)) {
                            $customerKyc->driving_license = $customerMati->driving_license;
                        }

                        if (isset($customerMati->omang)) {
                            $customerKyc->omang = $customerMati->omang;
                        }

                        if (isset($customerMati->omangBack)) {
                            $customerKyc->omangBack = $customerMati->omangBack;
                        }

                        if (isset($customerMati->proof_residence)) {
                            $customerKyc->proof_residence = $customerMati->proof_residence;
                        }

                        $saved = $customerKyc->save();
                    }
                }

                return Redirect::back()->with('success', 'Data fetched successfully.');
            } else {
                return Redirect::back()->with('error', 'Identity ID or Verification ID not found.');
            }
        } else {


            return Redirect::back()->with('error', 'Customer mati verification data not found.');
        }
    }
    public function updateMatiData(Request $request,$id)
    {
        if ($id != null) {
            $identity_id = $request->identity_id;
            $customer = Customer::where('id', $id)->first();
            if ($identity_id != null) {
                $customer->mati_identity = $identity_id;
            }
            $customer->save();

            $mati = CustomerMati::where('identity_id', $identity_id)->first();
            if (isset($mati)) {
                $mati->verification_id = $request->verification_id;
                $mati->save();
            } else {
                $mati = new CustomerMati();
                $mati->identity_id = $request->identity_id;
                $mati->verification_id = $request->verification_id;

                $curlToken = curl_init();

                curl_setopt_array($curlToken, array(
                    CURLOPT_URL => "https://api.getmati.com/oauth",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Basic NjExMjZjZmIzODNmZjgwMDFiMjdhNGJmOlBGN1VUWFZZMkdOUkZRQUs1QUE0VDRaWDFOS0VVSDgy",
                        "Content-Type: application/x-www-form-urlencoded"
                    ),
                ));

                $response = curl_exec($curlToken);
                $fetchToken = json_decode($response, true);
                curl_close($curlToken);

                if ($fetchToken['access_token'])
                    $token = $fetchToken['access_token'];
                else
                    return null;


                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.getmati.com/v2/verifications/' . $request->verification_id, //613f3b1475dc16001b45d7e4',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $token
                    ),
                ));

                $response = curl_exec($curl);
                $data = json_decode($response, true);
                curl_close($curl);

                $mati->response = json_encode($data);
                // dd($data);

                foreach ($data['documents'] as $key => $value) {
                    if ($value['type'] == 'passport') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                $image = Image::make($photo);
                                $image->encode('jpg');
                                $s3 = Storage::disk('s3');
                                $filePath = 'Mati/' . 'KYC' . '/' . $request->verification_id . '/passport';
                                $s3->put($filePath, $image->__toString(), 'public');
                                $mati->passport = $filePath;
                            }
                        }
                    }

                    if ($value['type'] == 'driving-license') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                $image = Image::make($photo);
                                $image->encode('jpg');
                                $s3 = Storage::disk('s3');
                                $filePath = 'Mati/' . 'KYC' . '/' . $request->verification_id . '/driving-license';
                                $s3->put($filePath, $image->__toString(), 'public');
                                $mati->driving_license = $filePath;
                            }
                        }
                    }


                    if ($value['type'] == 'national-id') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                if ($key == 0) {
                                    $image = Image::make($photo);
                                    $image->encode('jpg');
                                    $s3 = Storage::disk('s3');
                                    $filePath = 'Mati/' . 'KYC' . '/' . $request->verification_id . '/national-id';
                                    $s3->put($filePath, $image->__toString(), 'public');
                                    $mati->omang = $filePath;
                                }

                                if ($key == 1) {
                                    $image = Image::make($photo);
                                    $image->encode('jpg');
                                    $s3 = Storage::disk('s3');
                                    $filePath = 'Mati/' . 'KYC' . '/' . $request->verification_id . '/national-id-back';
                                    $s3->put($filePath, $image->__toString(), 'public');
                                    $mati->omangBack = $filePath;
                                }
                            }
                        }
                    }


                    if ($value['type'] == 'proof-of-residency') {
                        foreach ($value['photos'] as $key => $photo) {
                            if (isset($photo)) {
                                $image = Image::make($photo);
                                $image->encode('jpg');
                                $s3 = Storage::disk('s3');
                                $filePath = 'Mati/' . 'KYC' . '/' . $request->verification_id . '/proof-of-residency';
                                $s3->put($filePath, $image->__toString(), 'public');
                                $mati->proof_residence = $filePath;
                            }
                        }
                    }
                }


                foreach ($data['steps'] as $key => $step) {
                    if ($step['id'] == 'selfie') {
                        if (isset($step['data']['selfiePhotoUrl'])) {
                            $image = Image::make($step['data']['selfiePhotoUrl']);
                            $image->encode('jpg');
                            $s3 = Storage::disk('s3');
                            $filePath = 'Mati/' . 'KYC' . '/' . $request->verification_id . '/selfie';
                            $s3->put($filePath, $image->__toString(), 'public');
                            $mati->selfie = $filePath;
                        }
                    }
                }

                $mati->save();
                // dd($mati);
                if (isset($data['metadata']) && isset($data['metadata']['user_id'])) {
                    $customer = Customer::where('id', $data['metadata']['user_id'])->first();
                    if (isset($customer)) {
                        $customer->mati_identity = $mati->identity_id;
                        $customer->save();
                    }
                } else {
                    $customer = NULL;
                }

                $customer = Customer::where('mati_identity', $request->identity_id)->first();

                if ($customer != NULL)
                    $customerKyc = KYC::where('customer_id', $customer->id)->first();
                else
                    $customerKyc = NULL;



                if ($customer != NULL && $customerKyc != NULL && isset($customer->mati_identity)) {
                    $customerMati = CustomerMati::where('identity_id', $customer->mati_identity)->orderBy('id', 'desc')->first();

                    if (isset($customerMati)) {
                        if (isset($customerMati->passport)) {
                            $customerKyc->passport = $customerMati->passport;
                        }

                        if (isset($customerMati->driving_license)) {
                            $customerKyc->driving_license = $customerMati->driving_license;
                        }

                        if (isset($customerMati->omang)) {
                            $customerKyc->omang = $customerMati->omang;
                        }

                        if (isset($customerMati->omangBack)) {
                            $customerKyc->omangBack = $customerMati->omangBack;
                        }

                        if (isset($customerMati->proof_residence)) {
                            $customerKyc->proof_residence = $customerMati->proof_residence;
                        }

                        $saved = $customerKyc->save();
                    }
                }

            }
            return Redirect::back()->with('success', 'Mati Identity ID and Verification ID Updated Successfully.');
        } else {
            return Redirect::back()->with('error', 'Customer data not found.');
        }
    }
    public function checkCustomerExist(Request $request)
    {
        try {
            $profile = null;
            $firstName = $request->firstname;
            $lastName = $request->lastname;
            if (($request->cellphone != null && $request->cellphone != '') || ($request->email != null && $request->email != '')
            // || ($firstName != null && $firstName != '') || ($lastName != null && $lastName != '')
            ) {
                $profile = Customer::orderBy('id', 'asc');
                if ($request->cellphone != null && $request->cellphone != '')
                    $profile->orWhere('cellphone', $request->cellphone);
                if ($request->email != null && $request->email != '')
                    $profile->orWhere('email', $request->email);

                // $profile->orWhere(function ($query) use ($firstName, $lastName) {
                //     if ($firstName != null && $firstName != '')
                //         $query->where('firstName', $firstName);
                //     if ($lastName != null && $lastName != '')
                //         $query->where('lastName', $lastName);
                // });

                $profile = $profile->first(array('id', 'customer_category', 'is_blocked'));
            }

            if ($profile != null) {
                $profile->customer_id = $profile->id;
            }
            if ($profile == null) {
                if ($request->omang != null && $request->omang != '' && $request->passport != null && $request->passport != '') {
                    $profile = CustomerProfile::where('omang', $request->omang)->orWhere('passport', $request->passport)->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->omang == null  && $request->passport != null && $request->passport != '') {
                    $profile = CustomerProfile::where('passport', $request->passport)->orderBy('id', 'asc')->first(array('customer_id'));
                } elseif ($request->omang != null  && $request->omang != '' && $request->passport == null) {
                    $profile = CustomerProfile::where('omang', $request->omang)->orderBy('id', 'asc')->first(array('customer_id'));
                }
                if ($profile != NULL) {
                    $customer = Customer::where('id', $profile->customer_id)->orderBy('id', 'asc')->first(array('customer_category', 'is_blocked'));
                    $profile->customer_category = $customer->customer_category;
                    $profile->is_blocked = $customer->is_blocked;
                }
            }

             // Enable Mati
             $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
             $mati_enable = $mati_config->value;

            if ($profile != null && $profile->customer_id) {

                $policyInvalid = $this->PolicyBusinessValidations($profile->customer_id, $request->product_id);
                if($policyInvalid)
                {
                    return response()->json(['policy_exists' => 1, 'title' => 'Policy exists', 'message' => 'The customer already has purchased the specified product'], 403);
                }

                $profile_dob = CustomerProfile::where('customer_id',$profile->customer_id)->first(array('dob'));
                $dateOfBirth = isset($profile_dob->dob) ? $profile_dob->dob : null;

                if (isset($dateOfBirth)) {

                    if(!str_contains($dateOfBirth, '/')){
                        $dateOfBirth = Carbon::createFromFormat('Y-m-d', $dateOfBirth)->format('d/m/Y');
                    }

                    if($request->product_id == 1 || $request->product_id == 4 ){
                        $dateOfBCheck = null;
                        if(str_contains($dateOfBirth, '/')){
                            $dateOfBCheck = Carbon::createFromFormat('d/m/Y', $dateOfBirth)->format('Y-m-d');
                        } else {
                            $dateOfBCheck = $dateOfBirth;
                        }

                        $totalYears = Carbon::parse($dateOfBCheck)->age;

                        // if ($totalYears < 18 || $totalYears > 65) {
                        //     return response()->json(['age_limit' => 0, 'title' => 'Customer age', 'message' => 'Customer age is less than 18 or more than 65'], 403);
                        // }
                        if ($totalYears < 18 ) {
                            return response()->json(['age_limit' => 0, 'title' => 'Customer age', 'message' => 'Customer age is less than 18 '], 403);
                        }
                    }
                }

                if ($profile->customer_category == 2 && $request->brokerCode != null){
                    $user = User::where('id',$request->brokerCode)->where('create_high_risk_quote',1)->first();
                    if (isset($user)) {
                        $profile->customer_category = 0;
                    }
                }

                if ($profile->customer_category == 2 || $profile->is_blocked == 1) {
                    return response()->json(
                        [
                            'success' => 1,
                            'customer_id' => -1,
                        ],
                        200
                    );
                } else {
                    $customer = Customer::where('id', $profile->customer_id)->first(array('cellphone', 'email','mati_identity'));

                    if($mati_enable == 1){
                        $kyc = 0;
                    }else{
                        $kyc = null;
                    }

                    //$kyc = 0;
                    if(isset($customer->mati_identity) && $customer->mati_identity != NULL  && $customer->mati_identity != '')
                    {
                        if($mati_enable == 1){
                          $kyc = 0;
                        }else{
                          $kyc = 1;
                        }
                    }

                    // $customerkyc = KYC::where('customer_id', $profile->customer_id)->first(array('omang', 'passport'));
                    // if ($customerkyc != NULL) {
                    //     if ($customerkyc->omang != NULL)
                    //         $kyc = 1;
                    //     if ($customerkyc->passport != NULL)
                    //         $kyc = 1;
                    // }
                    if (!empty($request->email)) {
                        return response()->json(
                            [
                                'success' => 1,
                                'customer_id' => isset($profile->customer_id) ? $profile->customer_id : null,
                                'dob' => isset($dateOfBirth) ? $dateOfBirth : null,
                                'cellphone' => isset($customer->cellphone) ? $customer->cellphone : null,
                                'email' => isset($request->email) ? $request->email : null,
                                'kyc' => isset($kyc) ? $kyc : null,
                            ],
                            200
                        );
                   }else{
                        return response()->json(
                            [
                                'success' => 1,
                                'customer_id' => isset($profile->customer_id) ? $profile->customer_id : null,
                                'dob' => isset($dateOfBirth) ? $dateOfBirth : null,
                                'cellphone' => isset($customer->cellphone) ? $customer->cellphone : null,
                                'email' => isset($customer->email) ? $customer->email : null,
                                'kyc' => isset($kyc) ? $kyc : null,
                            ],
                            200
                        );
                    }
                }
            } else {
                return response()->json(
                    [
                        'success' => 1,
                        'customer_id' => null,
                        'kyc' => 0,
                    ],
                    200
                );
            }
        } catch (\Exception $ex) {
            return response()->json(
                [
                    'success' => 0,
                    'message' => $ex->getMessage() . ' ' . $ex->getLine()
                ],
                401
            );
        }
    }

    public function sendMailToBlockedCustomer(Request $request){
        $policy = Policy::whereDate('created_at', Carbon::today())->get(array('id','customer_id','policyNumber','product_id','premium','status','created_at'));
        if(count($policy)>0){
            $blockedCustomer = Customer::where('is_blocked',1)->get(array('id','firstName','middleName','lastName'));
            if(count($blockedCustomer)>0){
                foreach($blockedCustomer as $blocked){
                    $firstName[] =  $blocked->firstName;
                    $middleName[] =  $blocked->middleName;
                    $lastName[] =  $blocked->lastName;
                }
                $suspiciousCustomer = array();
                foreach($policy as $p){
                    if(( in_array($p->customer->firstName,array_filter($firstName, fn($value) => !is_null($value) && $value !== ''), true))
                       || ( in_array($p->customer->middleName,array_filter($middleName, fn($value) => !is_null($value) && $value !== ''), true))
                       || ( in_array($p->customer->lastName,array_filter($lastName, fn($value) => !is_null($value) && $value !== ''), true))){
                          // array_push($suspiciousCustomer,$p);
                          $suspiciousCustomer[]= $p;
                    }
                }
                if(!empty($suspiciousCustomer)){
                    //## Generate pdf
                    $data = [
                        'suspiciousCustomer'=>$suspiciousCustomer
                    ];
                    $date = \Carbon\Carbon::now()->timestamp;
                    $path = 'Policy/created-'.$date.'/SuspiciousCustomer.pdf';
                    $pdf = PDF::loadView('admin.notes.daily_suspicious_customer_info', $data);
                    Storage::disk('s3')->put($path, $pdf->output(), 'public');
                    $attachments = array();
                    array_push($attachments, $path);
                    $email = array();
                    $email = array(
                        'lambatnikita@gmail.com'
                    );
                    if(count($email) > 0) {
                        foreach($email as $d){
                            if($d){
                               //## Mail
                                // $email="lambatnikita@gmail.com";
                                $data = new \stdClass();
                                $data->hook = 'blacklist_alert';
                                $data->attachment = $attachments;
                                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                                $markdown = new MailTemplate($data);
                                $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
                                event(new \AlphaDirect\Events\SendMail($d,$emailTemplate->subject,"",$html,$attachments,['hook' => $data->hook]));
                            }
                        }
                    }
                }
            }else{
                return 'No block customers are available.';
            }
        }else{
            return 'No policies are available.';
        }
    }

    //old
    // public function checkCustomerExist(Request $request){
    //     try{

    //         $profile = null;
    //         if ($request->cellphone != null) {
    //             $profile = Customer::where('cellphone', $request->cellphone)->orderBy('id', 'asc')->first(array('id', 'customer_category','is_blocked'));
    //         }

    //         if ($profile != null) {
    //             $profile->customer_id = $profile->id;
    //         }
    //         if ($profile == null) {
    //             if ($request->omang != null && $request->passport != null) {
    //                 $profile = CustomerProfile::where('omang', $request->omang)->orWhere('passport', $request->passport)->orderBy('id', 'asc')->first(array('customer_id'));
    //             } elseif ($request->omang == null && $request->passport != null) {
    //                 $profile = CustomerProfile::where('passport', $request->passport)->orderBy('id', 'asc')->first(array('customer_id'));
    //             } elseif ($request->omang != null && $request->passport == null) {
    //                 $profile = CustomerProfile::where('omang', $request->omang)->orderBy('id', 'asc')->first(array('customer_id'));
    //             }
    //             if($profile != NULL) {
    //                 $customer = Customer::where('id', $profile->customer_id)->orderBy('id', 'asc')->first(array('customer_category','is_blocked'));
    //                 $profile->customer_category = $customer->customer_category;
    //                 $profile->is_blocked = $customer->is_blocked;
    //             }
    //         }

    //         if($profile != null && $profile->customer_id) {
    //             if($profile->customer_category == 2 || $profile->is_blocked == 1){
    //                 return response()->json(
    //                     [
    //                         'success' => 1,
    //                         'customer_id' => -1,
    //                     ], 200);
    //             }else{
    //                 $customer = Customer::where('id', $profile->customer_id)->first(array('cellphone', 'email'));
    //                 return response()->json(
    //                     [
    //                         'success' => 1,
    //                         'customer_id' => $profile->customer_id,
    //                         'cellphone' => $customer->cellphone,
    //                         'email' => $customer->email,
    //                     ], 200);
    //             }
    //         }
    //         else {
    //             return response()->json(
    //                 [
    //                     'success' => 1,
    //                     'customer_id' => null
    //                 ], 200);
    //         }
    //     }catch(\Exception $ex){
    //         return response()->json(
    //             [
    //                 'success' => 0,
    //                 'message'=>$ex->getMessage().' '.$ex->getLine()
    //             ], 401);
    //     }
    // }

    public function getCustomerData(Request $request)
    {
        try {
            $customer = Customer::join('customer_profile', 'customer_profile.customer_id', 'customer.id')
                ->where('customer.id', $request->customer_id)
                ->orderBy('customer.id', 'desc')
                ->first();

            return response()->json(
                [
                    'success' => 1,
                    'customer' => $customer
                ],
                200
            );
        } catch (\Exception $ex) {
            return response()->json(
                [
                    'success' => 0,
                    'message' => $ex->getMessage() . ' ' . $ex->getLine()
                ],
                401
            );
        }
    }

  /*
    Do not touch this api
  */
    public function getCustomerByCellphoneOrange(Request $request)
    {
        /*
        No input
             {
                "offerName": "mon offre préférée",
                "offerId": "abcdef1234",
                "offerDescription": "voici le détail de mon offre préférée",
                "amount": "100.5",
                "paymentFrequency": "month"
            }
        */
         #DB::enableQueryLog();
      #  dd($request);
        $records = Policy::join('customer', 'customer.id', 'policies.customer_id')
            ->join('products', 'products.id', 'policies.product_id')
            ->join('product_plans', 'products.id', 'product_plans.product_id')
            ->leftJoin('orangeMandate', 'policies.policyNumber' , 'orangeMandate.mandateId')
            ->Where('customer.cellphone', 'like', '%' . $request->cellphone . '%')
            ->Where('policies.status', '=', '0')
            ->where('orangeMandate.mandateId',"=",NULL)
            ->groupBy('policies.policyNumber')->get();
         # dd(DB::getQueryLog());
         #dd($records);
        $resultArr = array();
        $paymentC = new  PaymentController();
        $policyC = new PolicyController();


        try {
            if ($records != null) {
                $final = array();
                foreach ($records as $record) {
                    if($record->plan_id != 3 ){
                        $resultArr['contractID'] = $record->policyNumber;
                        $resultArr['offerName'] = $record->name;
                        $resultArr['offerId'] = $record->plan_id;
                        $resultArr['billingStartDate'] = $record->billingStartDate;
                        #$resultArr['offerDescription'] = $record->slug . " for " . $record->policyNumber;
                        $resultArr['offerDescription'] = $record->name . " for " . $record->policyNumber;
                        if ($record->plan_id == 3) {
                            $resultArr['amount'] =  $policyC->getMotorComprehensivePremium($record->policyNumber);
                        } else {
                            $resultArr['amount'] =  $record->product_id == 3 ? $record->premium : $paymentC->getPremiumByProductPlan($record->plan_id);
                        }
                        #$resultArr['paymentFrequency'] = $record->premium_freq == null ? "Month" : $this->gtPremiumFrquency($record->premium_freq);
                        $resultArr['paymentFrequency'] =  "Week";

                        array_push($final,$resultArr);
                     }
                }
                return response()->json($final, 200);
            } else {
                return response()->json(
                    [
                        'code' => 0,
                        'description' => 'Invalid MSIDN',
                        'message' =>  'No user found',
                    ],
                    27
                );
            }
        } catch (Exception $ex) {
            return response()->json(
                [
                    'success' => 0,
                    'message' => $ex->getMessage() . ' ' . $ex->getLine()
                ],
                401
            );
        }
    }


  /*
    Do not touch this api
  */
    public function getCustomerInfoByCellphoneOrange(Request $request)
    {
        /*
            {
                "debitorIdOnCreditorSide": "abc4587za",
                "offerName": "Offre Découverte",
                "offerId": "abcdef1234",
                "offerDescription": "voici le détail de mon offre préférée",
                "amount": "100.5",
                "paymentFrequency": "month",
                "contractID": "MIS2021013884"
            }

                {
                "creditorName": "Company name",
                "creditorAddress": "Siège Social, 14000 Abidjan",
                "IC": "Company001",
                "creditorId": "1a2b3c48f0",
                "mandateId": "123abc456def",
                "paymentDate": "2020-05-25"
                }
        */
        #DB::enableQueryLog();
      #  dd($request);
        if($request->contractID == null){
            return response()->json(
                [
                    'code' => 0,
                    'description' => 'Invalid Contract ID',
                    'message' =>  'No contract id found',
                ]
            );
        }
        $record = Policy::join('customer', 'customer.id', 'policies.customer_id')
            ->join('customer_profile','customer_profile.customer_id','customer.id')
            ->Where('policies.policyNumber', 'like', '%' . $request->contractID . '%')
            ->first();
        #dd($record);
           # dd(DB::getQueryLog());
        $resultArr = array();
        try {
            if ($record != null) {
                    $resultArr['contractID'] = $record->policyNumber;
                   # $resultArr['creditorName'] = $record->firstName." ".$record->middleName." ".$record->lastName;
                    $resultArr['creditorName'] = "Alphadirect";
                    $resultArr['creditorAddress'] = $record->address;
                    $resultArr['IC'] = "AlphaDirect";
                    $resultArr['creditorId'] = env('ORANGE_CREDITOR_ID');#$record->customer_id;
                    $resultArr['mandateId']=  $record->policyNumber;
                    $resultArr['paymentDate'] = $record->billingStartDate;
                return response()->json($resultArr, 200);
            } else {
                return response()->json(
                    [
                        'code' => 0,
                        'description' => 'Invalid MSIDN',
                        'message' =>  'No user found',
                    ]
                );
            }
        } catch (Exception $ex) {
            return response()->json(
                [
                    'success' => 0,
                    'message' => $ex->getMessage() . ' ' . $ex->getLine()
                ],
                401
            );
        }
    }


    public function gtPremiumFrquency($freg)
    {
        if ($freg == 1) {
            return "Monthly";
        } elseif ($freg == 2) {
            return '3 Installments';
        } elseif ($freg == 3) {
            return 'Yearly';
        } else {
            return "Monthly";
        }
    }


    public function setting_percentage()
    {
        $data=\AlphaDirect\Lookup::where('key','customer_cashback_percentage')->first('value');
        return view('admin.customerCashback.setting', compact(['data']));
    }

    public function settingPercentage(request $request){
         $setting=\AlphaDirect\Lookup::where('key','customer_cashback_percentage')->first();
         $setting->value=$request->name;
         $setting->save();
         return Redirect::back()->with('success','Percentage set successfully');
    }

    public function customerCashback_data()
    {
        $data=\AlphaDirect\Models\CustomerCashback::all();

        return DataTables::of($data)
        ->editColumn('id',function($data){
            return $data->id;
        })->editColumn('policy_id',function($data){
            $policy=\AlphaDirect\Policy::find($data->policy_id);
            return $policy->policyNumber;
        })->editColumn('customer_id',function($data){
            $customer=\AlphaDirect\Customer::find($data->customer_id);
            return ucwords(strtolower($customer->firstName)).' '.ucwords(strtolower($customer->lastName));
        })->editColumn('premium',function($data){
            return $data->premium;
        })->editColumn('reward_points',function($data){
            return $data->reward_points;
        })->editColumn('reward_date',function($data){
            return $data->reward_date;
        })->editColumn('created_at',function($data){
            return \Carbon\Carbon::parse($data->created_at)->format('Y-m-d');
        })->editColumn('updated_at',function($data){
            return \Carbon\Carbon::parse($data->updated_at)->format('Y-m-d');
        })
        ->rawColumns(['id','policy_id','customer_id','premium','reward_points'])->make(true);
    }

    public function customerCashback()
    {
        return view('admin.customerCashback.rewards');
    }

    public function allcustomersearch()
    {
        return view('admin.customer.customer_search');
    }

    public function customersearchdata(Request $request)
    {
      //  dd($request->all());
        $data = Customer::leftJoin('customer_profile', 'customer_profile.customer_id', 'customer.id')
        ->leftJoin('policies','policies.customer_id','customer.id')
        ->leftJoin('products','products.id','policies.product_id')
        ->orderBy('customer.id', 'desc');

        $name = $request->name;
        $email = $request->email;
        $cellphone = $request->cellphone;
        $omangpassport = $request->omangpassport;

        $data->where(function($query) use ($name) {
            if($name != null){
                $query->where('customer.firstName', '=','%'.$name.'%')
                ->orWhere('customer.lastName', '=','%'.$name.'%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.middleName,customer.lastName)'), 'like', '%' . $name . '%')
                ->orWhere(DB::raw('CONCAT_WS(" ", customer.firstName,customer.lastName)'), 'like', '%' . $name . '%');
            }
        })->where(function ($query) use ($email) {
            if($email != null){
                $query->where('customer.email', 'like', '%'.$email.'%');
            }
        })->where(function ($query) use ($cellphone) {
            if($cellphone != null){
                $query->where('customer.cellphone', 'like', '%'.$cellphone.'%');
            }
        })->where(function ($query) use ($omangpassport) {
            if($omangpassport != null){
                $query->where('customer_profile.omang', 'like', '%'.$omangpassport.'%')
                ->orWhere('customer_profile.passport', 'like', '%' .$omangpassport. '%');
            }
        });

        if( isset($name) && $name != null || isset($email) && $email != null  || isset($cellphone) && $cellphone != null || isset($omangpassport) && $omangpassport != null){
            $customer = $data->get(['customer.id','customer.firstName','customer.middleName','customer.lastName','customer.cellphone','customer.email','policies.id as policy_id','policies.policyNumber','products.name','policies.status','customer.is_blocked']);
        }else{
            $customer = [];
        }

        return DataTables::of($customer)

        ->addColumn('firstName', function ($customer)
        {
            if($customer->firstName != null || $customer->firstName != 'Null'){
                $firstName = $customer->firstName;
            }
            if($customer->middleName != null || $customer->middleName != 'Null'){
                $middleName = $customer->middleName;
            }
            if($customer->lastName != null || $customer->lastName != 'Null'){
                $lastName = $customer->lastName;
            }

            $name = '<a href="' . route('admin.customer.edit', $customer['id']) . '" target="_blank"><span class=""></span>&nbsp&nbsp' . $firstName . ' ' . $middleName . ' ' . $lastName . '</a>';
            return $name;
        })

        ->addColumn('policyNumber', function ($customer)
        {
            if ($customer->policyNumber != null) {
                $policyNumber =  '<a href="' . route('admin.policy.policyView', $customer['policy_id']) . '" target="_blank"><span class=""></span>&nbsp&nbsp' . $customer->policyNumber . '</a>';
            }else{
                $policyNumber = '-';
            }
            return $policyNumber;
        })

        ->addColumn('status', function ($customer)
        {
            $policy_status =  $customer->status;
            if ($policy_status == 1) {
                $policy_status = '<span class="kt-font-bold kt-font-brand">Activated</span>';
            }
            elseif ($policy_status == 2) {
                $policy_status = '<span class="kt-font-bold kt-font-danger">Cancel</span>';
            }
            elseif ($policy_status == 3) {
                $policy_status = '<span class="kt-font-bold kt-font-danger">Expired</span>';
            }
            elseif ($policy_status == 0) {
                $policy_status = '<span class="kt-font-bold kt-font-danger">Deactivated</span>';
            } else {
                $policy_status = '<span class="kt-font-bold kt-font-focus">Deactivated - KYC Pending</span>';
            }
            return $policy_status;
        })

        ->addColumn('is_blocked', function ($customer)
        {
            $is_blocked     =  $customer->is_blocked;
            if($is_blocked == 1){
                $is_blocked ='<span class="kt-font-bold kt-font-danger">Blocked</span>';
            }else{
                $is_blocked  = '<span class="kt-font-bold kt-font-focus">Unblock</span>';
            }
            return $is_blocked;
        })

        ->rawColumns(['firstName','is_blocked','status','policyNumber'])
        ->make(true);
    }

    public function changecustomerpolicy()
    {
        $records = Customer::orderBy('id', 'DESC')->get(['id','firstName','lastName','email',]);
        $Policys = Policy::orderBy('id', 'DESC')->get(['id','policyNumber',]);

        return view('admin.customer.change_customer_policy',compact('records', 'Policys'));
    }

    public function changecustomerpolicystore(Request $request)
    {
        $Policys = Policy::where('policyNumber', $request->policy_number)->first();
        $Policys->customer_id = $request->customer_id;
        $Policys->save();
        $Kyc = KYC::where( 'customer_id', $request->customer_id )->first();
        $Kyc->compliance = 2;
        $Kyc->status = 'Recheck';
        $Kyc->save();
        activity('Customer in Policy set' )
        ->performedOn($Policys)
        ->log('Customer in Policy set');
        return Redirect::route('admin.policy.policyView',$Policys->id)->with('success','Customer in Policy set successfully');

    }
    public function customerdetail(Request $request)
    {

         $data1  = Customer::where('id', $request->get('id'))->orderBy('id', 'desc')->first();
         $data2  = CustomerProfile::where('customer_id', $request->get('id') )->first();
         $dob = date("d-m-Y", strtotime($data2->dob));
         $city = $data2->city;
         if ($city != null) {

            if (is_numeric($city)) {
                $cityn = City::where('id', $city)->first();
                if ($cityn && $cityn->name) {
                    $city = $cityn->name;
                } else {
                    $city = null;
                }
            } else {
                $city = $city;
            }
        } else {
            $city = null;
        }

         return response()->json(['status' => 'success', 'id' => $request->get('id'), 'data1' => $data1, 'data2' => $data2, 'dob' => $dob , 'city' => $city]);
    }
    public function policydetail(Request $request)
    {

        $data1  = Policies::where('policyNumber', $request->get('id'))->first();
        if(isset($data1->billingStartDate)){
        $billingStartDate = date("d-m-Y", strtotime($data1->billingStartDate));
        }else{
            $billingStartDate = '';
        }

         return response()->json(['status' => 'success', 'id' => $request->get('id'), 'data1' => $data1, 'billingStartDate' => $billingStartDate ]);
    }
    public function changecustomerpolicystoreconform(Request $request)
    {
        $body = 'Are you sure want to replace the existing customer data with new this new customer data?';
            return response()->json(['status' => 'success', 'id' => $request->get('id'), 'body' => $body]);

    }
    public function customerdetaillist(Request $request)
    {

        if(isset($request->name)){
            $data1  = Customer::where('firstName', 'like', '%' . $request->name . '%')
                              ->orwhere('lastName', 'like', '%' . $request->name . '%')->get();
        }

        if(isset($request->email)){
            $data1  = Customer::where('email', 'like', '%' . $request->email . '%')->get();
            }
        if(isset($request->cellphone)){
            $data1  = Customer::where('cellphone', 'like', '%' . $request->cellphone . '%')->get();
                }
        if(isset($request->omangpassport)){
            $data2  = CustomerProfile::where('omang', $request->omangpassport)
            ->orwhere('passport', $request->omangpassport)
            ->first();
            $data1  = Customer::where('id',  $data2->customer_id )->get();
            }
            return response()->json(['status' => 'success', 'data1' => $data1]);
    }

    public function checkExsitingCustomer(Request $request)
    {
        try {
            $profile = null;
            if ($request->get('phone') != null) {
                $profile = Customer::where('cellphone', $request->get('phone'))->orderBy('id', 'asc')->first();
            }

            if ($profile != null) {
                // $profile->customer_id = $profile->id;
                 $profile = CustomerProfile::where('customer_id', $profile->id)->orderBy('id', 'asc')->first();
            }

            if ($profile == null) {
                if ($request->get('omang') != null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orWhere('passport', $request->get('passport'))->orderBy('id', 'asc')->first();
                } elseif ($request->get('omang') == null && $request->get('passport') != null) {
                    $profile = CustomerProfile::where('passport', $request->get('passport'))->orderBy('id', 'asc')->first();
                } elseif ($request->get('omang') != null && $request->get('passport') == null) {
                    $profile = CustomerProfile::where('omang', $request->get('omang'))->orderBy('id', 'asc')->first();
                }
            }

            if ($request->get('email') != null && $profile != null) {
                $profile = Customer::where('email', $request->get('email'))->orderBy('id', 'asc')->first();
                if ($profile != null) {
                    // $profile->customer_id = $profile->id;
                     $profile = CustomerProfile::where('customer_id', $profile->id)->orderBy('id', 'asc')->first();
                }
            }

            // Enable Mati
            $mati_config = Config::where('key','enable_mati')->first(array('id','value'));
            $mati_enable = $mati_config->value;

            if($mati_enable == 1){
                $kyc = 0;
            }else{
                $kyc = 1;
            }

            if ($profile != NULL) {
                $customer = Customer::where('id', $profile->customer_id)->first('mati_identity');
                if(isset($customer->mati_identity) && $customer->mati_identity != NULL  && $customer->mati_identity != '')
                {
                    $kyc = 1;
                }elseif($mati_enable == 1){
                    $kyc = 0;
                }
            }

            $is_user_check = 0;
            if ($profile != NULL) {
                $checkPolicy = Policy::where('customer_id', $profile->customer_id)->get();
                if ($checkPolicy != NULL && isset($checkPolicy->policyNumber)) {
                    $is_user_check = 1;
                } else {
                    $is_user_check = 0;
                }
            } else {
                $is_user_check = 0;
            }

            if ($profile != NULL) {
                $is_user = 1;
                if ($profile->password != null) {
                    $is_user = 1;
                } else {
                    $is_user = 0;
                }
            } else {
                $is_user = 0;
            }

            $flow_id = null;
            $policy_product = null;
            if (isset($request->product_id)) {
                $product = Product::where('id', $request->product_id)->first();
                $compliance = KycCompliance::where('id', $product->kyc_compliance)->first(array('flow_id'));
                if($compliance != NULL && $compliance->flow_id > 0 && $compliance->flow_id != NULL)
                    $flow_id = $compliance->flow_id;
                else {
                    if(env('APP_STATUS') == 'Production')
                        $flow_id = '616ac99406694f001be574c7'; // AdvanceKYC LIVE
                    else
                        $flow_id = '612c87b1ebca36001b310ea1'; // LiveQuote Test
                }
            }
            $customerExists='';$pNum='';
            if ($profile != null) {
                $customerData = Customer::where('id', $profile->customer_id)->first(array('firstName', 'middleName', 'lastName', 'email', 'cellphone'));
                $userKyc = KYC::where('customer_id', $profile->customer_id)->first('compliance');

                if (isset($request->product_id)) {
                    if ($request->product_id == 4 || $request->product_id == 1) {
                        $row = Policy::where('customer_id',$profile->customer_id)->where('product_id', $request->product_id)->where('status', "!=", 2)->count();
                        if ($row > 0) {

                            $policy_product = 'present';
                            if ($request->planId == 13 || $request->planId == 16) {

                                $rows = Policy::where('customer_id',$profile->customer_id)->whereIn('plan_id',array("1","4"))->where('status', "!=", 2)->count();
                                if ($rows > 0) {
                                    $policyNumber = Policy::where('customer_id',$profile->customer_id)->whereIn('plan_id',array("1","4"))->where('status', "!=", 2)->first('policyNumber');//->where('status', "!=", 2)
                                    $pNum=$policyNumber->policyNumber;
                                    $policy_product = 'present';
                                    $customerExists=2;
                                }
                            }

                        }
                    } else if ($request->product_id == 2) {
                        if ($request->planId == 16) {
                        $rows = Policy::where('customer_id',$profile->customer_id)->where('plan_id',4)->where('status', "!=", 2)->count();
                            if ($rows > 0) {
                                $policyNumber = Policy::where('customer_id',$profile->customer_id)->whereIn('plan_id',array("1","4"))->where('status', "!=", 2)->first('policyNumber');//->where('status', "!=", 2)
                                $pNum=$policyNumber->policyNumber;
                                $policy_product = 'present';
                                $customerExists=2;
                            }
                        }
                    }

                }

                $customerArray = [
                    'policy_product' => $policy_product,
                    'count' => $profile,
                    'customerData' => $customerData,
                    'userkyc' => $userKyc,
                    'kyc' => $kyc,
                    'is_user' => $is_user,
                    'is_user_check' => $is_user_check,
                    'customerExists' =>  $customerExists=='2'? $customerExists:'1',
                    'flow_id' => $flow_id,
                    'policyNumber' => $pNum
                ];
                return $customerArray;
            } else {
                $customerArray = [
                    'policy_product' => $policy_product,
                    'kyc' => $kyc,
                    'is_user' => $is_user,
                    'is_user_check' => $is_user_check,
                    'customerExists' => '0',
                    'flow_id' => $flow_id
                ];
                return $customerArray;
            }
        } catch (Exception $ex) {
            return response()->json($ex->getMessage(), 500);
        }
    }


    public function PolicyBusinessValidations($customer_id,$product_id)
    {
        try {
            $row = Policy::where('customer_id',$customer_id)->where('product_id', $product_id)->where('status', "!=", 2)->count();

            switch ($product_id) {
                case 1: //AdI
                    if ($row > 0) {
                        return true;
                    }else{
                        return false;
                    }
                    break;

                // case 2: // Third Party
                //     if ($row > 0) {
                //         return true;
                //     }else{
                //         return false;
                //     }
                //     break;

                case 4: //Legal
                    if ($row > 0) {
                        return true;
                    }else{
                        return false;
                    }
                    break;

                // case 5: //Mobile Cellphone
                //     if ($row > 0) {
                //         return true;
                //     }else{
                //         return false;
                //     }
                //     break;
                default:
                    return false;
                    break;
            }

        } catch (\Exception $ex) {
            return true;
        }
    }

}
