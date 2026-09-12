<?php

namespace AlphaDirect\Http\Controllers;

use Illuminate\Http\Request;
use AlphaDirect\Models\CancelPolicyRequest;
use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use Yajra\DataTables\DataTables;
use Auth;
use Redirect;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerFeedback;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Customer;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use Carbon\Carbon;
use Http\Client\Exception;
use AlphaDirect\Http\Controllers\Admin\PolicyController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Product;

class CancelPolicyRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
       
        if (auth::user()->hasPermissionTo('cancelpolicyrequest_list')) {
           
            return view('admin.CancelPolicyRequest.index');
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

 

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'policy_id' => 'required|string',
            
            // 'reason' => 'nullable|string',
            // 'circumstances' => 'nullable|string',
            // 'other_company' => 'nullable|string',
            // 'status' => 'required|in:pending,approved,rejected',
        ]);
        if($request->policy_id){
            if(Policy::where('id',$request->policy_id)->where('status','!=',2)->exists()){
                $policy = Policy::where('id',$request->policy_id)->first();
                $exist = CancelPolicyRequest::where('policyNumber', $policy->policyNumber)->whereIn('status',['pending','approved'])->first();
                if($exist){
                    return response()->json(['success' => 0,'message' => 'Cancel Policy Request already inserted.'], 201);
                }else{
                   
                  $cancel = new CancelPolicyRequest();
                  $cancel->requestdata = json_encode($request->all());
                  $cancel->policyNumber =$policy->policyNumber;
                  $cancel->reason =$request->reason ?? null;
                  $cancel->other_company =$request->other_company ?? null;
                  $cancel->circumstances =$request->circumstances ?? null;
              
                  $cancel->status ="pending";
                  $cancel->save();
                    return response()->json(['success' => 1,'message' => 'Cancel Policy Request created successfully.'], 200);
                }
            }else{
                return response()->json(['success' => 0,'message' => 'Cancel Policy Request Failed dew to policy number not found.'], 401);
            }
        }else{
            return response()->json(['success' => 0,'message' => 'Cancel Policy Request Failed dew to policy number not found.'], 400);

        }
    
       
    }

    /**
     * approved the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approved($id)
    {
        if (auth::user()->hasPermissionTo('cancelpolicyrequest_approved')) {
        $cancel =  CancelPolicyRequest::where('id',$id)->first();
        $cancel->status  = 'approved';
        $cancel->action_by = auth()->user()->id;
        $cancel->save();
       
        $this->customerFeedbackFromStart(json_decode($cancel->requestdata));
        return Redirect::route('admin.cancelpolicyrequests.index')->with('success', 'Approved Successfully');
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    
    }

    /**
     * decline the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function decline($id)
    {
        if (auth::user()->hasPermissionTo('cancelpolicyrequest_decline')) {
            $cancel =  CancelPolicyRequest::where('id',$id)->first();
            $cancel->status  = 'rejected';
            $cancel->action_by = auth()->user()->id;
            $cancel->save();
            return Redirect::route('admin.cancelpolicyrequests.index')->with('success', 'rejected Successfully');
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

 
    public function cancelPolicyRequestData1()
    {
        // Ensure the user has the correct permission
        if (auth::user()->hasPermissionTo('Cron_Mail_list')) {

            // Fetch all CancelPolicyRequest records
            $cancelPolicyRequests = CancelPolicyRequest::orderBy('id','desc')
            ->get([
                'id',
                'policyNumber',
                'reason',
                'circumstances',
                'other_company',
                'status'
              ]);

            // Generate the DataTable response
            return DataTables::of($cancelPolicyRequests)
                
                // Format the 'circumstances' column (example: add line breaks for each circumstance)
                ->editColumn('circumstances', function ($request) {
                    return $request->circumstances ? nl2br($request->circumstances) : '-';
                })

                // Handle 'status' column formatting
                

                // Add actions for edit and delete with permissions
                ->addColumn('actions', function ($data) {
                    $actions = '';
                   
                   if($data->status == "pending"){

                if (auth::user()->hasPermissionTo('cancelpolicyrequest_approved')) {
                    // Check if the user can edit
                    $actions .= '<a href="' . route('cancelpolicyrequests.approved', $data->id) . '" 
                        class="btn btn-success mr-2" title="Approve">
                        Approve
                    </a>';
                }

                if (auth::user()->hasPermissionTo('cancelpolicyrequest_decline')) {
                  $actions .= '<a href="' . route('cancelpolicyrequests.decline', $data->id) . '" 
                        class="btn btn-danger" title="Decline">
                        Decline
                    </a>';
                }
                }
                    return $actions;
                })

                // Define columns where HTML should be rendered
                ->rawColumns(['actions', 'circumstances'])
                ->make(true);

        } else {
            // Return a permission error if unauthorized
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function cancelPolicyRequestData(Request $request)
    {
        
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
        $totalRecords = CancelPolicyRequest::select('count(*) as allcount')->count();
        # DB::enableQueryLog();
        // Fetch records
        $records = CancelPolicyRequest::leftJoin('policies', 'policies.policyNumber', 'cancel_policy_requests.policyNumber')
        
        ->orderBy('cancel_policy_requests.id', 'DESC');
        $product = []; 

        if (auth()->user()->hasPermissionTo('cancelpolicyrequest_motorcomp_policy')) {
            $product = array_merge($product, [3]);
        }
        
        if (auth()->user()->hasPermissionTo('cancelpolicyrequest_instant_policy')) {
            $product = array_merge($product, [1, 2, 4, 5]);
        }
        
       
        $records = $records->whereIn('policies.product_id', $product);
        

        $totalRecordswithFilter = $records->count();

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get(
                [
                'cancel_policy_requests.id',
                'cancel_policy_requests.policyNumber',
                'policies.product_id',
                'cancel_policy_requests.reason',
                'cancel_policy_requests.circumstances',
                'cancel_policy_requests.other_company',
                'cancel_policy_requests.status',
                'cancel_policy_requests.action_by',
                'cancel_policy_requests.created_at',
                'cancel_policy_requests.updated_at'
                ]
                );

        $records = json_decode($records, true);
        $data_arr = array();
        $sno = $start + 1;
        foreach ($records as $record) {
          

            $actions = '';
                   
            if($record['status'] == "pending"){
                $actions .= '<a href="' . route('cancelpolicyrequests.approved', $record['id']) . '" 
                 class="btn btn-success mr-2" title="Approve">
                    Approve
                </a>';
                $actions .= '<a href="' . route('cancelpolicyrequests.decline', $record['id']) . '" 
                 class="btn btn-danger" title="Decline">
                    Decline
                </a>';
            }else{
                if(isset($record['action_by']) && $record['action_by']!=null ){
                    $user = User::where('id', $record['action_by'])->first(['firstName','lastName']);
                    if($user){
                        $actions .=  $user->firstName.' '. $user->lastName;
                    }
                    
                }
               
            }
            $productName = null;
            $product = Product::where('id',$record['product_id'])->first(['name']);
            if($product){
                $productName = $product->name;
            }
           
            $data_arr[] = array(
                'id' =>$record['id'],
                'policyNumber' =>$record['policyNumber'],
                'product' =>$productName,
                'reason' =>$record['reason'],
                'circumstances' =>$record['circumstances'],
                'other_company' =>$record['other_company'],
                'status' =>$record['status'],
                'actions' =>$actions,
                'created_at' => Carbon::parse($record['created_at'])->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($record['updated_at'])->format('Y-m-d H:i:s')
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
    public function customerFeedbackFromStart($request)
    {
        try{
            DB::beginTransaction();

            $policyToCancel = Policy::where('id', $request->policy_id)->first();
            if ($policyToCancel) {
                // if (!isset($request->fromstart) || $request->fromstart != "yes") {
                  
                //   return $this->customerFeedback($request);
                // }
                if ($request->customer_id != null) {
                    $feedback              = new CustomerFeedback();
                    $feedback->policy_id   = $request->policy_id;
                    $feedback->customer_id = $request->customer_id;
                    $feedback->product_id  = $request->product_id;

                   
                    $feedback->reason = $request->reason ?? null;
                
                
                    $feedback->circumstances = $request->circumstances ?? null;
                
                
                    $feedback->other_company = $request->other_company ?? null;
                    $feedback->cancelled_by = auth()->user()->firstName .' ' .auth()->user()->lastName;
                  
                    $feedback->save();

                 

                    $action_user = null;
                    $action_customer = $request->customer_id;
                    event(new \AlphaDirect\Events\policyLifecycle($request->policy_id,"Cancel",$action_user,$action_customer));

                    $policyToCancel->status = 2;
                    $policyToCancel->save();
                    $customer = Customer::where('id', $request->customer_id)->first();
                    if($customer){
                    if($customer->email != null){
                    $data = new \stdClass();
                    $data->policy_id = $request->policy_id;
                    $data->customer_id = $customer->id;
                    $data->hook = 'cancel_policy';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policyToCancel->policyNumber,'hook' => $data->hook]));
                   // Mail::to($customer->email)->send(new MailTemplate($data));
                    }
                    //sms
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailPolicyCancelled($customer->cellphone,$customer->firstName,$policyToCancel->policyNumber);

                    }

                    $pc     = new PolicyController();
                    $update = $pc->updatePolicyDates($policyToCancel->policyNumber, 2);
                    $cancelstatus =  $pc->CancelPaymentsForPolicy($policyToCancel);
                    DB::commit();

                    $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first();
                    if (isset($banking)) {
                        return response()->json(['success' => 1, 'paymentMethod' => $banking->billing], 200);
                    } else {
                        return response()->json(['success' => 1, 'paymentMethod' => null], 200);
                    }

                    // return response()->json(['success' => 1], 200);

                } else {
                    return response()->json(['success' => 0, 'paymentMethod' => null], 401);
                }
            } else {
                return response()->json(['success' => 0, 'paymentMethod' => null], 401);
            }
        }catch(Exception $x)
        {
            DB::rollback();
        }
    }
    public function customerFeedback($request)
    {
        try {
            DB::beginTransaction();
            $banking = CustomerBanking::where('policy_id', $request->policy_id)->first();
            $policyToCancel = Policy::where('id', $request->policy_id)->first();

            $policyToCancel->status = 2;
            $cancelled = $policyToCancel->save();

            if ($cancelled) {
                if ($request->customer_id != null) {
                    $customer = Customer::where('id', $request->customer_id)->first();
                    $data = new \stdClass();
                    $data->policy_id = $request->policy_id;
                    $data->customer_id = $customer->id;
                    $data->hook = 'cancel_policy';
                    $data->attachment = NULL;
                    $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(array('subject'));
                    $markdown = new MailTemplate($data);
                    $html = $markdown->render('Mail.mailTemplate',['data'=>$data]);
	                event(new \AlphaDirect\Events\SendMail($customer->email,$emailTemplate->subject,"",$html,NULL,['policyNumber' => $policyToCancel->policyNumber,'hook' => $data->hook]));
                   // Mail::to($customer->email)->send(new MailTemplate($data));

                    //sms
                    $sms = new SmsMessaging();
                    $sms->SendSMSEmailPolicyCancelled($customer->cellphone,$customer->firstName,$policyToCancel->policyNumber);
                    // $sms->SendSMSEmailPolicyCancelled(24, $policyToCancel->policyNumber, $customer->firstName, $customer->cellphone);

                    $feedback              = new CustomerFeedback();
                    $feedback->policy_id   = $request->policy_id;
                    $feedback->customer_id = $request->customer_id;
                    $feedback->product_id  = $request->product_id;

                   
                    $feedback->reason = $request->reason ?? null;
                
                
                    $feedback->circumstances = $request->circumstances ?? null;
                
                
                    $feedback->other_company = $request->other_company ?? null;
                  
                    $feedback->save();


                 
                    $action_user = null;
                    $action_customer = $request->customer_id;
                    event(new \AlphaDirect\Events\policyLifecycle($request->policy_id,"Cancel",$action_user,$action_customer));

                    $pc = new PolicyController();
                    $update = $pc->updatePolicyDates($policyToCancel->policyNumber, 2);
                    $cancelstatus =  $pc->CancelPaymentsForPolicy($policyToCancel);
                    DB::commit();

                    $banking = CustomerBanking::where('policy_id', $policyToCancel->id)->first();
                    if (isset($banking)) {
                        return response()->json(['success' => 1, 'paymentMethod' => $banking->billing, 'message' => 'Cancellation successful. Please log into VCS system and MANUALLY cancel the transaction.Feedback submitted successfully'], 200);
                    } else {
                        return response()->json(['success' => 1, 'paymentMethod' => null,'message' => 'Sucess! Your policy cancelled successfully'], 200);
                    }

                    // return response()->json(['success' => 1], 200);
                } else {
                    return response()->json(['success' => 0, 'paymentMethod' => null, 'message' => 'Something went wrong'], 401);
                }
            } else {
                return response()->json(['success' => 0, 'paymentMethod' => null, 'message' => 'Something went wrong'], 401);
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['success' => 0, 'message' => $ex,'paymentMethod' => null, 'message' => 'Something went wrong'], 401);
        }
    }
}
