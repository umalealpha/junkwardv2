<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\User;
use AlphaDirect\Models\HrUser;
use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Models\EmployerGroupPolicy;
use AlphaDirect\Product;
use AlphaDirect\Policy;
use AlphaDirect\Productplan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Customer;
use AlphaDirect\Vehicle;
use AlphaDirect\KYC;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Transaction;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\AdGroupedPolicyBeneficiary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;

class HrController extends Controller
{
    public function showLoginForm()
    {
        return view('hr.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $credentials = $request->only('email', 'password');

        // Debug: Check if user exists
        $user = \AlphaDirect\Models\HrUser::where('email', $credentials['email'])->first();
        if (!$user) {
            \Log::info('HR Login Failed: User not found', ['email' => $credentials['email']]);
            return back()->withErrors([
                'email' => 'No HR account found with this email address.',
            ])->onlyInput('email');
        }

        if (!$user->is_active) {
            \Log::info('HR Login Failed: User inactive', ['email' => $credentials['email']]);
            return back()->withErrors([
                'email' => 'Your account is inactive. Please contact administrator.',
            ])->onlyInput('email');
        }

        if (!$user->password) {
            \Log::info('HR Login Failed: No password set', ['email' => $credentials['email']]);
            return back()->withErrors([
                'email' => 'Please set your password first using the link sent to your email.',
            ])->onlyInput('email');
        }

        if (Auth::guard('hr')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            \Log::info('HR Login Successful', ['email' => $credentials['email']]);
            return redirect()->intended(route('hr.ad-group-policy'));
        }

        \Log::info('HR Login Failed: Invalid credentials', ['email' => $credentials['email']]);
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showSetPasswordForm(Request $request)
    {
        $encodedEmail = $request->get('email');
        $expires = $request->get('expires');
        $signature = $request->get('signature');

        if (!$encodedEmail) {
            return redirect()->route('hr.login')->with('error', 'Email parameter is required.');
        }

        // Decode base64 encoded email
        $email = base64_decode($encodedEmail);

        if (!$email) {
            return redirect()->route('hr.login')->with('error', 'Invalid email parameter.');
        }

        // Validate expiry (48 hours) and signature
        if (!$expires || !$signature) {
            return redirect()->route('hr.login')->with('error', 'Invalid or missing link parameters.');
        }
        if (now()->timestamp > (int)$expires) {
            return redirect()->route('hr.login')->with('error', 'This link has expired. Please request a new one.');
        }
        $expected = hash_hmac('sha256', $email.'|'.$expires, config('app.key'));
        if (!hash_equals($expected, $signature)) {
            return redirect()->route('hr.login')->with('error', 'Invalid link signature.');
        }

        $user = HrUser::where('email', $email)
                   ->where('is_active', true)
                   ->first();

        if (!$user) {
            return redirect()->route('hr.login')->with('error', 'Invalid email address.');
        }

        return view('hr.set-password', [
            'email' => $user->email
        ]);
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'expires' => 'required|integer',
            'signature' => 'required|string'
        ]);

        // Validate expiry and signature again on submit
        if (now()->timestamp > (int)$request->expires) {
            return back()->withErrors(['email' => 'This link has expired. Please request a new one.']);
        }
        $expected = hash_hmac('sha256', $request->email.'|'.$request->expires, config('app.key'));
        if (!hash_equals($expected, $request->signature)) {
            return back()->withErrors(['email' => 'Invalid link signature.']);
        }

        $user = HrUser::where('email', $request->email)
                   ->where('is_active', true)
                   ->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Invalid email address.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('hr.login')->with('success', 'Password set successfully. You can now login.');
    }


    public function logout(Request $request)
    {
        Auth::guard('hr')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('hr.login');
    }

    public function showPasswordRequestForm()
    {
        return view('hr.password-request');
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = HrUser::where('email', $request->email)
                   ->where('is_active', true)
                   ->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'No HR account found with this email address.',
            ]);
        }

        $expires = now()->addHours(48)->timestamp;
        $signature = hash_hmac('sha256', $user->email.'|'.$expires, config('app.key'));
        $resetLink = url('/hr/set-password?email=' . base64_encode($user->email) . '&expires=' . $expires . '&signature=' . $signature);

        // Use existing email system - following ReKYC pattern
        $emailData = new \stdClass();
        $emailData->user_id = $user->id;
        $emailData->customer_id = null;
        $emailData->hook = 'hr_login_new';
        $emailData->email = $user->email;
        $emailData->attachment = null;

        // Add HR-specific data
        $emailData->reset_link = $resetLink;

        $emailTemplate = EmailBroadcasting::where('hook_slug', $emailData->hook)->first();
        if (!$emailTemplate) {
            \Log::error('HR Email Template Not Found', [
                'hook' => $emailData->hook,
                'available_templates' => EmailBroadcasting::pluck('hook_slug')->toArray()
            ]);
            throw new \Exception('Email template not found for hook: ' . $emailData->hook);
        }

        $markdown = new MailTemplate($emailData);
        $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);
        event(new \AlphaDirect\Events\SendMail($user->email, $emailTemplate->subject, "", $html, null, ['hook' => $emailData->hook]));

        return back()->with('success', 'Password reset link sent to your email address.');
    }

    public function adGroupPolicy()
    {
        $products = Product::with('type')->where('status', 1)->get(array('id', 'product_type_id', 'name', 'is_motor_items', 'kyc_customer', 'has_vehicle', 'has_subApplicant'));
        $policies = Policy::all();
        $product_plans = Productplan::where('status', 1)->where('product_id', 12)->whereNotIn('id',[14,15])->get();
        $agents = Policy::join('users', 'users.id', 'policies.agent_id')
            ->where('users.active', 1)
            ->where('policies.agent_id', '!=', 'null')
            ->groupBy('policies.agent_id')
            ->get();
        // Get employer groups for quote generation - only show the logged-in HR user's group
        $hrUser = Auth::guard('hr')->user();
        $employerGroups = collect();
        if ($hrUser && $hrUser->employer_group_id) {
            $employerGroup = $hrUser->employerGroup;
            if ($employerGroup) {
                $employerGroups = collect([$employerGroup]);
                \Log::info('HR User employer group for view', [
                    'hr_user_email' => $hrUser->email,
                    'employer_group_id' => $employerGroup->id,
                    'employer_group_code' => $employerGroup->employer_group_id,
                    'employer_group_name' => $employerGroup->name
                ]);
            } else {
                \Log::error('HR User employer group not found', [
                    'hr_user_email' => $hrUser->email,
                    'hr_user_employer_group_id' => $hrUser->employer_group_id
                ]);
            }
        } else {
            \Log::error('HR User not found or no employer group ID', [
                'hr_user' => $hrUser ? $hrUser->toArray() : null
            ]);
        }

        return view('hr.adGroupIndex', compact('products', 'policies', 'agents', 'product_plans', 'employerGroups'));
    }

    public function debugHrUser()
    {
        $hrUser = Auth::guard('hr')->user();
        if (!$hrUser) {
            return response()->json(['error' => 'Not authenticated']);
        }

        // Find all employer groups
        $allEmployerGroups = EmployerGroup::select('id', 'employer_group_id', 'name')->get();
        
        // Find CIMOXOQQN group
        $cimoxoqqnGroup = EmployerGroup::where('employer_group_id', 'CIMOXOQQN')->first();
        
        return response()->json([
            'hr_user' => [
                'id' => $hrUser->id,
                'email' => $hrUser->email,
                'employer_group_id' => $hrUser->employer_group_id,
                'current_employer_group' => $hrUser->employerGroup ? [
                    'id' => $hrUser->employerGroup->id,
                    'employer_group_id' => $hrUser->employerGroup->employer_group_id,
                    'name' => $hrUser->employerGroup->name
                ] : null
            ],
            'all_employer_groups' => $allEmployerGroups,
            'cimoxoqqn_group' => $cimoxoqqnGroup ? [
                'id' => $cimoxoqqnGroup->id,
                'employer_group_id' => $cimoxoqqnGroup->employer_group_id,
                'name' => $cimoxoqqnGroup->name
            ] : null
        ]);
    }

    public function adGroupPolicyData(Request $request)
    {
        // Get the logged-in HR user
        $hrUser = Auth::guard('hr')->user();
        
        if (!$hrUser || !$hrUser->employerGroup) {
            \Log::warning('HR User not found or no employer group assigned', [
                'hr_user' => $hrUser ? $hrUser->toArray() : null
            ]);
            return response()->json([
                'draw' => $request->get('draw'),
                'iTotalRecords' => 0,
                'iTotalDisplayRecords' => 0,
                'aaData' => []
            ]);
        }

        \Log::info('HR Policy data request', [
            'hr_user_id' => $hrUser->id,
            'hr_user_email' => $hrUser->email,
            'employer_group_id' => $hrUser->employerGroup->employer_group_id,
            'employer_group_name' => $hrUser->employerGroup->name
        ]);

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

        // Total records - filtered by HR user's employer group
        $totalRecords = EmployerGroupPolicy::where('employer_group_id', $hrUser->employerGroup->employer_group_id)->count();
        # DB::enableQueryLog();
        // Fetch records - filtered by HR user's employer group
        $records = EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
            ->where('employer_group_policy.employer_group_id', $hrUser->employerGroup->employer_group_id) // Filter by HR user's employer group
            ->orderBy('policies.id', 'DESC')
            ->leftJoin('customer', 'customer.id', 'policies.customer_id')
            ->leftJoin('products', 'products.id', 'policies.product_id')
            ->leftJoin('vehicle', 'vehicle.policy_id', 'policies.id')
            ->leftJoin('employer_groups', 'employer_groups.employer_group_id', 'employer_group_policy.employer_group_id');

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

        if ($request->lead_agent != '-1') {
            $records->where('policies.leadAgentID', $request->lead_agent);
        }
        // Removed admin permission-based product restrictions for HR portal
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
                    'policies.premium',
                    'policies.created_at',
                    'employer_groups.name as employer_group_name'
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
            // HR portal: show View and Edit actions with proper spacing
            $actions .= '<a href="' . route('admin.policy.policyView', $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View" style="margin-right: 8px; display: inline-block;">
                            <i class="la la-eye"></i>
                        </a>';

            // Use full URL for edit button with proper styling
            $actions .= '<a href="' . url('/policy/edit/' . $id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit" style="margin-left: 8px; display: inline-block;">
                            <i class="la la-pencil"></i>
                        </a>';

            // HR portal: skip Archive action

            // HR portal: skip Claim actions
            // HR portal: skip Super Admin-specific actions
            $employer_group = isset($record['employer_group_name']) ? $record['employer_group_name'] : null;
            $data_arr[] = array(
                "id"              => $id,
                "view"            => $view,
                "productPlanName" => $productPlanName,
                "name"            => $name,
                "cellphone"       => $cell,
                "product_name"    => $productName,
                "agentName"       => $agentName,
                "status"          => $status,
                "premium"         => isset($record['premium']) ? 'P ' . number_format($record['premium'], 2) : 'N/A',
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

    public function generateUserPoliciesPDF(Request $request)
    {
        // Implementation for generating user policies PDF
        // This would be similar to the admin version but for HR users
        return response()->json(['message' => 'PDF generation not implemented yet']);
    }

    public function getQuoteData(Request $request)
    {
        try {
            // Get the logged-in HR user
            $hrUser = Auth::guard('hr')->user();
            
            if (!$hrUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            \Log::info('HR Quote data request received', [
                'hr_user_id' => $hrUser->id,
                'hr_user_email' => $hrUser->email,
                'hr_user_employer_group_id' => $hrUser->employer_group_id,
                'request_employer_group_id' => $request->employer_group_id,
                'policy_status' => $request->policy_status
            ]);

            // Get the employer group details for the HR user
            $employerGroup = $hrUser->employerGroup;

            if (!$employerGroup) {
                \Log::error('HR User has no employer group', [
                    'hr_user_id' => $hrUser->id,
                    'hr_user_email' => $hrUser->email,
                    'hr_user_employer_group_id' => $hrUser->employer_group_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No employer group assigned to your account'
                ], 400);
            }

            \Log::info('HR User employer group details', [
                'employer_group_id' => $employerGroup->id,
                'employer_group_code' => $employerGroup->employer_group_id,
                'employer_group_name' => $employerGroup->name
            ]);

            $query = EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
                ->leftJoin('customer', 'customer.id', 'policies.customer_id')
                ->leftJoin('customer_profile', 'customer_profile.customer_id', 'policies.customer_id')
                ->leftJoin('employer_groups', 'employer_groups.employer_group_id', 'employer_group_policy.employer_group_id')
                ->leftJoin('product_plans', 'product_plans.id', 'policies.plan_id')
                ->leftJoin('ad_grouped_policy_beneficiary', 'ad_grouped_policy_beneficiary.policy_id', 'policies.id')
                ->select([
                    'policies.id',
                    'policies.policyNumber as policy_number',
                    'policies.premium',
                    'customer.firstName',
                    'customer.lastName',
                    'customer_profile.dob',
                    'customer_profile.gender',
                    'employer_groups.name as employer_group_name',
                    'employer_groups.employer_group_id as employer_group_code',
                    'employer_group_policy.employee_id',
                    'employer_group_policy.documentation_fee',
                    'employer_group_policy.interaction_fee',
                    'employer_group_policy.levy_fee',
                    \DB::raw('COUNT(ad_grouped_policy_beneficiary.id) as total_beneficiaries'),
                    \DB::raw('CONCAT(customer.firstName, " ", customer.lastName) as employee_name'),
                    \DB::raw('CASE 
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 0 AND 19 THEN "0-19"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 20 AND 29 THEN "20-29"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 30 AND 39 THEN "30-39"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 40 AND 49 THEN "40-49"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 50 AND 54 THEN "50-54"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 55 AND 59 THEN "55-59"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 60 AND 64 THEN "60-64"
                        ELSE "65+"
                    END as age_group'),
                    \DB::raw('CASE 
                        WHEN customer_profile.gender = 1 THEN "M"
                        WHEN customer_profile.gender = 0 THEN "F"
                        ELSE "N/A"
                    END as gender'),
                    'product_plans.name as health_plan'
                ])
                ->where('policies.is_test_policy', 0)
                ->where('policies.product_id', 12) // AD Group product
                ->where('employer_group_policy.employer_group_id', $employerGroup->employer_group_id) // Filter by HR user's employer group code
                ->groupBy('policies.id', 'policies.policyNumber', 'policies.premium', 'product_plans.name', 'customer.firstName', 'customer.lastName', 'customer_profile.dob', 'customer_profile.gender', 'employer_groups.name', 'employer_groups.employer_group_id', 'employer_group_policy.employee_id', 'employer_group_policy.documentation_fee', 'employer_group_policy.interaction_fee', 'employer_group_policy.levy_fee');

            // Apply additional filters (these will be within the HR user's employer group)
            if ($request->employer_group_id) {
                // Only allow filtering within the same employer group
                if ($request->employer_group_id == $employerGroup->employer_group_id) {
                    $query->where('employer_group_policy.employer_group_id', $request->employer_group_id);
                }
            }

            if ($request->policy_status !== null && $request->policy_status !== '') {
                $query->where('policies.status', $request->policy_status);
            }

            $policies = $query->get();

            // Debug: Check if there are any policies for this employer group
            $totalPoliciesForGroup = EmployerGroupPolicy::where('employer_group_id', $employerGroup->employer_group_id)->count();
            $totalPoliciesForProduct = EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
                ->where('employer_group_policy.employer_group_id', $employerGroup->employer_group_id)
                ->where('policies.product_id', 12)
                ->where('policies.is_test_policy', 0)
                ->count();

            \Log::info('HR Quote data query result', [
                'hr_user_id' => $hrUser->id,
                'hr_user_employer_group_id' => $hrUser->employer_group_id,
                'employer_group_code' => $employerGroup->employer_group_id,
                'employer_group_name' => $employerGroup->name,
                'total_policies_for_group' => $totalPoliciesForGroup,
                'total_policies_for_product' => $totalPoliciesForProduct,
                'filtered_policies_count' => $policies->count(),
                'first_policy' => $policies->first()
            ]);

        return response()->json([
            'success' => true,
            'data' => $policies,
            'debug' => [
                'hr_user_email' => $hrUser->email,
                'employer_group_code' => $employerGroup->employer_group_id,
                'employer_group_name' => $employerGroup->name,
                'total_policies_for_group' => $totalPoliciesForGroup,
                'total_policies_for_product' => $totalPoliciesForProduct
            ]
        ]);

        } catch (\Exception $e) {
            \Log::error('Error in HR getQuoteData', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading quote data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateQuoteReport(Request $request)
    {
        try {
            $policyIds = $request->input('policy_ids', []);
            
            if (empty($policyIds)) {
                return redirect()->back()->with('error', 'No policies selected for quote generation.');
            }

            // Get the logged-in HR user to filter by their employer group
            $hrUser = Auth::guard('hr')->user();
            if (!$hrUser || !$hrUser->employerGroup) {
                return redirect()->back()->with('error', 'No employer group assigned to your account.');
            }

            $policies = EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
                ->leftJoin('customer', 'customer.id', 'policies.customer_id')
                ->leftJoin('customer_profile', 'customer_profile.customer_id', 'policies.customer_id')
                ->leftJoin('employer_groups', 'employer_groups.employer_group_id', 'employer_group_policy.employer_group_id')
                ->leftJoin('product_plans', 'product_plans.id', 'policies.plan_id')
                ->leftJoin('ad_grouped_policy_beneficiary', 'ad_grouped_policy_beneficiary.policy_id', 'policies.id')
                ->select([
                    'policies.id',
                    'policies.policyNumber as policy_number',
                    'policies.premium',
                    'customer.firstName',
                    'customer.lastName',
                    'customer_profile.dob',
                    'customer_profile.gender',
                    'employer_groups.name as employer_group_name',
                    'employer_groups.employer_group_id as employer_group_code',
                    'employer_group_policy.employee_id',
                    'employer_group_policy.documentation_fee',
                    'employer_group_policy.interaction_fee',
                    'employer_group_policy.levy_fee',
                    \DB::raw('COUNT(ad_grouped_policy_beneficiary.id) as total_beneficiaries'),
                    \DB::raw('CONCAT(customer.firstName, " ", customer.lastName) as employee_name'),
                    \DB::raw('CASE 
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 0 AND 19 THEN "0-19"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 20 AND 29 THEN "20-29"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 30 AND 39 THEN "30-39"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 40 AND 49 THEN "40-49"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 50 AND 54 THEN "50-54"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 55 AND 59 THEN "55-59"
                        WHEN TIMESTAMPDIFF(YEAR, customer_profile.dob, CURDATE()) BETWEEN 60 AND 64 THEN "60-64"
                        ELSE "65+"
                    END as age_group'),
                    \DB::raw('CASE 
                        WHEN customer_profile.gender = 1 THEN "M"
                        WHEN customer_profile.gender = 0 THEN "F"
                        ELSE "N/A"
                    END as gender'),
                    'product_plans.name as health_plan'
                ])
                ->whereIn('policies.id', $policyIds)
                ->where('policies.is_test_policy', 0)
                ->where('policies.product_id', 12)
                ->where('employer_group_policy.employer_group_id', $hrUser->employerGroup->employer_group_id) // Filter by HR user's employer group
                ->groupBy('policies.id', 'policies.policyNumber', 'policies.premium', 'product_plans.name', 'customer.firstName', 'customer.lastName', 'customer_profile.dob', 'customer_profile.gender', 'employer_groups.name', 'employer_groups.employer_group_id', 'employer_group_policy.employee_id', 'employer_group_policy.documentation_fee', 'employer_group_policy.interaction_fee', 'employer_group_policy.levy_fee')
                ->orderBy('policies.created_at', 'DESC')
                ->get();

            // Prepare data for PDF
            $data = [
                'policies' => $policies,
                'user' => (object) [
                    'firstName' => $hrUser->first_name,
                    'lastName' => $hrUser->last_name,
                    'email' => $hrUser->email
                ],
                'generated_at' => now()->format('d-m-Y H:i:s'),
                'total_premium' => $policies->sum('premium'),
                'total_beneficiaries' => $policies->sum('total_beneficiaries'),
                'total_documentation_fee' => $policies->sum('documentation_fee'),
                'total_interaction_fee' => $policies->sum('interaction_fee'),
                'total_levy_fee' => $policies->sum('levy_fee'),
                'employer_group_name' => $policies->first()->employer_group_name ?? $hrUser->employerGroup->name
            ];

            // Generate PDF
            $pdf = PDF::loadView('admin.reports.quote_report_pdf', $data);
            
            return $pdf->download('quote_report_' . now()->format('Y_m_d_H_i_s') . '.pdf');
            
        } catch (\Exception $e) {
            \Log::error('Error in HR generateQuoteReport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error generating quote report: ' . $e->getMessage());
        }
    }

    /**
     * Show policy update page with Excel-like interface
     */
    public function policyUpdate()
    {
        $hrUser = Auth::guard('hr')->user();
        
        if (!$hrUser || !$hrUser->employerGroup) {
            return redirect()->route('hr.ad-group-policy')->with('error', 'No employer group assigned to your account.');
        }

        return view('hr.policyUpdate', [
            'employerGroup' => $hrUser->employerGroup
        ]);
    }

    /**
     * Get policy data for update interface
     */
    public function policyUpdateData(Request $request)
    {
        $hrUser = Auth::guard('hr')->user();
        
        \Log::info('Policy Update Data Request', [
            'hr_user' => $hrUser ? $hrUser->email : 'Not authenticated',
            'request_data' => $request->all()
        ]);
        
        if (!$hrUser || !$hrUser->employerGroup) {
            \Log::warning('HR User not found or no employer group', [
                'hr_user' => $hrUser ? $hrUser->toArray() : null
            ]);
            return response()->json([
                'draw' => $request->get('draw'),
                'iTotalRecords' => 0,
                'iTotalDisplayRecords' => 0,
                'aaData' => []
            ]);
        }

        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $searchValue = trim($request->get('search')['value'] ?? '');

        // Get policy data for the HR user's employer group with beneficiary details
        $query = EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
            ->leftJoin('customer', 'customer.id', 'policies.customer_id')
            ->leftJoin('customer_profile', 'customer_profile.customer_id', 'policies.customer_id')
            ->leftJoin('product_plans', 'product_plans.id', 'policies.plan_id')
            ->leftJoin('ad_grouped_policy_beneficiary', 'ad_grouped_policy_beneficiary.policy_id', 'policies.id')
            ->where('employer_group_policy.employer_group_id', $hrUser->employerGroup->employer_group_id)
            ->where('policies.is_test_policy', 0)
            ->where('policies.product_id', 12); // AD Group product

        // Apply search filter
        if ($searchValue) {
            $query->where(function($q) use ($searchValue) {
                $q->where('policies.policyNumber', 'like', '%' . $searchValue . '%')
                  ->orWhere('customer.firstName', 'like', '%' . $searchValue . '%')
                  ->orWhere('customer.lastName', 'like', '%' . $searchValue . '%')
                  ->orWhere('customer.cellphone', 'like', '%' . $searchValue . '%');
            });
        }

        // Count total records before grouping
        $totalRecords = EmployerGroupPolicy::join('policies', 'employer_group_policy.policyNumber', 'policies.policyNumber')
            ->where('employer_group_policy.employer_group_id', $hrUser->employerGroup->employer_group_id)
            ->where('policies.is_test_policy', 0)
            ->where('policies.product_id', 12)
            ->count();
        
        \Log::info('Policy Update Query Results', [
            'employer_group_id' => $hrUser->employerGroup->employer_group_id,
            'total_records' => $totalRecords
        ]);
        
        $totalRecordswithFilter = $totalRecords;

        $records = $query->groupBy('policies.id')
            ->skip($start)
            ->take($rowperpage)
            ->get([
                'policies.id',
                'policies.policyNumber',
                'policies.premium',
                'policies.status',
                'policies.plan_id',
                'customer.firstName',
                'customer.lastName',
                'customer.cellphone',
                'customer.email',
                'customer_profile.dob',
                'customer_profile.gender',
                'product_plans.name as plan_name',
                \DB::raw('COUNT(ad_grouped_policy_beneficiary.id) as beneficiary_count'),
                \DB::raw('GROUP_CONCAT(CONCAT(ad_grouped_policy_beneficiary.first_name, " ", ad_grouped_policy_beneficiary.last_name) SEPARATOR ", ") as beneficiary_names'),
                \DB::raw('GROUP_CONCAT(ad_grouped_policy_beneficiary.dob SEPARATOR ", ") as beneficiary_dobs'),
                \DB::raw('GROUP_CONCAT(ad_grouped_policy_beneficiary.gender SEPARATOR ", ") as beneficiary_genders')
            ]);

        $data_arr = [];
        foreach ($records as $record) {
            $data_arr[] = [
                'id' => $record->id,
                'policy_number' => $record->policyNumber,
                'customer_name' => trim(($record->firstName ?? '') . ' ' . ($record->lastName ?? '')),
                'plan_name' => $record->plan_name ?? 'N/A',
                'premium' => $record->premium,
                'dob' => $record->dob,
                'gender' => $record->gender,
                'beneficiary_names' => $record->beneficiary_names ?? '',
                'beneficiary_dobs' => $record->beneficiary_dobs ?? '',
                'beneficiary_genders' => $record->beneficiary_genders ?? ''
            ];
        }

        // Debug logging
        \Log::info('Policy Update Data Response', [
            'hr_user_id' => $hrUser->id,
            'employer_group_id' => $hrUser->employerGroup->employer_group_id,
            'total_records' => $totalRecords,
            'records_count' => count($records),
            'data_count' => count($data_arr),
            'first_record' => $records->first(),
            'first_data' => $data_arr[0] ?? null
        ]);

        return response()->json([
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        ]);
    }

    /**
     * Handle bulk policy updates
     */
    public function policyUpdateBulk(Request $request)
    {
        try {
            $hrUser = Auth::guard('hr')->user();
            
            if (!$hrUser || !$hrUser->employerGroup) {
                return response()->json([
                    'success' => false,
                    'message' => 'No employer group assigned to your account.'
                ], 400);
            }

            $updates = $request->input('updates', []);
            $updatedCount = 0;
            $errors = [];
            
            \Log::info('Policy Update Bulk Request', [
                'hr_user_id' => $hrUser->id,
                'employer_group_id' => $hrUser->employerGroup->employer_group_id,
                'updates_count' => count($updates),
                'updates' => $updates
            ]);

            foreach ($updates as $update) {
                try {
                    $policyId = $update['policy_id'];
                    $field = $update['field'];
                    $value = $update['value'];
                    
                    \Log::info('Processing update', [
                        'policy_id' => $policyId,
                        'field' => $field,
                        'value' => $value
                    ]);

                    // Verify the policy belongs to the HR user's employer group
                    $policy = Policy::join('employer_group_policy', 'employer_group_policy.policyNumber', 'policies.policyNumber')
                        ->where('policies.id', $policyId)
                        ->where('employer_group_policy.employer_group_id', $hrUser->employerGroup->employer_group_id)
                        ->first();

                    if (!$policy) {
                        $errorMsg = "Policy ID {$policyId} not found or not accessible";
                        $errors[] = $errorMsg;
                        \Log::warning('Policy not found', [
                            'policy_id' => $policyId,
                            'employer_group_id' => $hrUser->employerGroup->employer_group_id
                        ]);
                        continue;
                    }
                    
                    \Log::info('Policy found', [
                        'policy_id' => $policyId,
                        'policy_number' => $policy->policyNumber,
                        'customer_id' => $policy->customer_id
                    ]);

                    // Update based on the field type
                    if (strpos($field, 'customer_') === 0) {
                        // Update customer information
                        $customerField = str_replace('customer_', '', $field);
                        $customer = Customer::find($policy->customer_id);
                        
                        if (!$customer) {
                            $errorMsg = "Customer not found for policy {$policyId}";
                            $errors[] = $errorMsg;
                            \Log::warning('Customer not found', [
                                'policy_id' => $policyId,
                                'customer_id' => $policy->customer_id
                            ]);
                            continue;
                        }
                        
                        \Log::info('Customer found', [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->firstName . ' ' . $customer->lastName
                        ]);
                        
                        switch ($customerField) {
                            case 'first_name':
                                $customer->firstName = $value;
                                break;
                            case 'last_name':
                                $customer->lastName = $value;
                                break;
                            case 'cellphone':
                                $customer->cellphone = $value;
                                break;
                            case 'email':
                                $customer->email = $value;
                                break;
                            case 'dob':
                                // Update customer profile DOB
                                $customerProfile = $customer->customerProfile;
                                if ($customerProfile) {
                                    $customerProfile->dob = $value;
                                    $customerProfile->save();
                                } else {
                                    $errors[] = "Customer profile not found for policy {$policyId}";
                                    continue 2;
                                }
                                break;
                            case 'gender':
                                // Update customer profile gender
                                $customerProfile = $customer->customerProfile;
                                if ($customerProfile) {
                                    $customerProfile->gender = $value;
                                    $customerProfile->save();
                                } else {
                                    $errors[] = "Customer profile not found for policy {$policyId}";
                                    continue 2;
                                }
                                break;
                            default:
                                $errors[] = "Invalid customer field: {$customerField}";
                                continue 2;
                        }
                        
                        $customer->save();
                        \Log::info('Customer updated successfully', [
                            'customer_id' => $customer->id,
                            'field' => $customerField,
                            'value' => $value
                        ]);
                        
                    } elseif (strpos($field, 'beneficiary_') === 0) {
                        // Update beneficiary information
                        $beneficiaryField = str_replace('beneficiary_', '', $field);
                        
                        // Handle comma-separated beneficiary values
                        if (in_array($beneficiaryField, ['names', 'dobs', 'genders'])) {
                            // Get all beneficiaries for this policy
                            $beneficiaries = AdGroupedPolicyBeneficiary::where('policy_id', $policyId)->get();
                            
                            if ($beneficiaries->isEmpty()) {
                                $errors[] = "No beneficiaries found for policy {$policyId}";
                                continue;
                            }
                            
                            $values = explode(',', $value);
                            
                            foreach ($beneficiaries as $index => $beneficiary) {
                                if (isset($values[$index])) {
                                    $newValue = trim($values[$index]);
                                    
                                    switch ($beneficiaryField) {
                                        case 'names':
                                            // Split name into first and last name
                                            $nameParts = explode(' ', $newValue, 2);
                                            $beneficiary->first_name = $nameParts[0] ?? '';
                                            $beneficiary->last_name = $nameParts[1] ?? '';
                                            break;
                                        case 'dobs':
                                            $beneficiary->dob = $newValue;
                                            break;
                                        case 'genders':
                                            $beneficiary->gender = $newValue;
                                            break;
                                    }
                                    
                                    $beneficiary->save();
                                }
                            }
                        } else {
                            // Handle individual beneficiary updates (legacy)
                            $beneficiaryId = $update['beneficiary_id'] ?? null;
                            
                            if (!$beneficiaryId) {
                                $errors[] = "Beneficiary ID required for beneficiary updates";
                                continue;
                            }
                            
                            $beneficiary = AdGroupedPolicyBeneficiary::find($beneficiaryId);
                            
                            if (!$beneficiary) {
                                $errors[] = "Beneficiary not found with ID {$beneficiaryId}";
                                continue;
                            }
                            
                            switch ($beneficiaryField) {
                                case 'first_name':
                                    $beneficiary->first_name = $value;
                                    break;
                                case 'last_name':
                                    $beneficiary->last_name = $value;
                                    break;
                                case 'relationship':
                                    $beneficiary->relation = $value;
                                    break;
                                case 'dob':
                                    $beneficiary->dob = $value;
                                    break;
                                case 'gender':
                                    $beneficiary->gender = $value;
                                    break;
                                default:
                                    $errors[] = "Invalid beneficiary field: {$beneficiaryField}";
                                    continue 2;
                            }
                            
                            $beneficiary->save();
                        }
                        
                    } else {
                        // Update policy information (excluding premium)
                        switch ($field) {
                            case 'status':
                                $policy->status = intval($value);
                                break;
                            case 'plan_id':
                                $policy->plan_id = intval($value);
                                break;
                            default:
                                $errors[] = "Invalid field: {$field}";
                                continue 2;
                        }
                        
                        $policy->save();
                    }

                    $updatedCount++;
                    \Log::info('Update count incremented', [
                        'updated_count' => $updatedCount,
                        'policy_id' => $policyId,
                        'field' => $field
                    ]);

                } catch (\Exception $e) {
                    $errors[] = "Error updating policy {$policyId}: " . $e->getMessage();
                }
            }

            \Log::info('Policy Update Bulk Response', [
                'updated_count' => $updatedCount,
                'errors_count' => count($errors),
                'errors' => $errors
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Updated {$updatedCount} policies successfully",
                'updated_count' => $updatedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in policyUpdateBulk', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating policies: ' . $e->getMessage()
            ], 500);
        }
    }
}
