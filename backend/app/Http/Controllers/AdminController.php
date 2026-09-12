<?php
namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\GlassClaim;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\KYC;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPlan;
use AlphaDirect\SalesTarget;
use AlphaDirect\Services\CacheService;
use AlphaDirect\Staff;
use AlphaDirect\Treaty;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Input;
use Pnlinh\InfobipSms\Facades\InfobipSms;
use Session;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;
use AlphaDirect\OTPTemp;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
            $otps = OTPTemp::first();
            $timestamp = strtotime($otps->otp_exp);
            $cDate = strtotime(date('Y-m-d H:i:s'));

            if($timestamp < $cDate){
             $otpx = random_int(0, 999999);
             //dd($otpx);
             $d = strtotime("+1 day");
             $dt = date("Y-m-d h:i:sa", $d);
             $otp= new OTPTemp();
             $count =  $otp->count();
             if($count > 0)
             {
                $status = $otp->first()->update([ 'otp' => $otpx , 'otp_exp' => $dt]);
             }else{
                 $status = $otp->create([ 'otp' => $otpx , 'otp_exp' => $dt]);
             }
          }
        $policies = Policy::orderBy('policyNumber', 'desc')
            ->paginate(10);

        // Single grouped query with Redis cache (5 min TTL) — invalidated by PolicyObserver
        $policyCounts = CacheService::remember('dashboard_policy_counts', function () {
            return DB::table('policies')->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as deactive,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as expired
            ')->first();
        }, CacheService::CACHE_TTL_SHORT);  // no tags — file driver compatible
        $totalPoliciesCount    = $policyCounts->total ?? 0;
        $activePoliciesCount   = $policyCounts->active ?? 0;
        $deactivePoliciesCount = $policyCounts->deactive ?? 0;
        $cancelPoliciesCount   = $policyCounts->cancelled ?? 0;
        $expiredPoliciesCount  = $policyCounts->expired ?? 0;

        //sales target
        $salesTarget = SalesTarget::latest()->first();
        if ($salesTarget) {
            $currentSalesTarget = $salesTarget->target_no - $totalPoliciesCount;
            $target_no = $salesTarget->target_no;
            $deadline_date = $salesTarget->deadline_date;
            $date = new Carbon();
            if ($deadline_date < $date) {

                $currentSalesTarget = 0;
                $deadline_date = null;
                $target_no = 0;
            }
        } else {
            $currentSalesTarget = 0;
            $deadline_date = null;
            $target_no = 0;
        }

        // Single grouped query with Redis cache (5 min TTL)
        $claimCounts = CacheService::remember('dashboard_claim_counts', function () {
            return DB::table('claims')->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = "Pending"  THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN claim_type = "Life"     THEN 1 ELSE 0 END) as life,
                SUM(CASE WHEN claim_type = "Accident" THEN 1 ELSE 0 END) as motor,
                SUM(CASE WHEN claim_type = "Glass"    THEN 1 ELSE 0 END) as glass
            ')->first();
        }, CacheService::CACHE_TTL_SHORT);
        $totalClaimCount    = $claimCounts->total ?? 0;
        $approvedClaimCount = $claimCounts->approved ?? 0;
        $rejectedClaimCount = $claimCounts->rejected ?? 0;
        $pendingClaimCount  = $claimCounts->pending ?? 0;
        $lifeClaimCount     = $claimCounts->life ?? 0;
        $motorClaimCount    = $claimCounts->motor ?? 0;
        $glassClaimCount    = $claimCounts->glass ?? 0;

        $policyLatest = Policy::join('customer', 'policies.customer_id', 'customer.id')
            ->join('products', 'policies.product_id', 'products.id')
            ->orderBy('policies.id', 'desc')
            ->take(5)
            ->get(array('policies.id', 'products.name', 'policies.status', 'customer.firstName', 'customer.lastName', 'policies.policyNumber'));

        $ClaimLatest = Claim::join('customer', 'claims.customer_id', 'customer.id')
            ->orderBy('claims.id', 'desc')
            ->take(5)
            ->get(array('claims.id', 'claims.status', 'customer.firstName', 'customer.lastName', 'claims.claim_number'));

        return view('admin/admin-dashboard', compact('policies', 'ClaimLatest','expiredPoliciesCount','activePoliciesCount', 'glassClaimCount', 'deactivePoliciesCount',
            'policyLatest', 'totalPoliciesCount', 'lifeClaimCount', 'motorClaimCount', 'cancelPoliciesCount', 'totalClaimCount',
            'approvedClaimCount', 'rejectedClaimCount', 'pendingClaimCount', 'currentSalesTarget', 'deadline_date', 'target_no'));
    }

    public function storeSalesTarget(Request $request)
    {
        $this->validate($request, [
            'target_no' => 'required',
            'deadline_date' => 'required',
        ]);
        $salesTarget = $request->target_no;
        $soldPolicies = Policy::where('status', 1)->count();
        $deadline_date = Carbon::parse($request->get('deadline_date'))->format('Y-m-d');

        $sales_target = new SalesTarget();
        $sales_target->target_no = $salesTarget;
        $sales_target->deadline_date = $deadline_date;
        $sales_target->created_by = auth()->user()->id;
        $sales_target->save();

        return redirect()->route('admin-dashboard')->with('success', 'New Sales target created');

    }

    public function setSalesTarget()
    {
        return view('admin.set_sales_target');
    }

    public function fileUpload()
    {
        $apkFiles = DB::table('apkFileUploads')->orderBy('created_at', 'desc')->get();
        foreach ($apkFiles as $file) {
            # code...
            $user = User::findOrFail($file->created_by);
        }

        return view('admin/file_upload', compact('apkFiles', 'user'));
    }

    public function policydata()
    {
        $policy = Policy::with(['customer', 'kyc', 'product'])
            ->limit(10)
            ->orderBy('policyNumber', 'desc')
            ->get(['id', 'customer_id', 'product_id', 'has_vehicle', 'has_member', 'policyNumber', 'status', 'created_at']);
        return DataTables::of($policy)
            ->editColumn('created_at', function ($policy) {
                return $policy->created_at->diffForHumans();
            })
            ->editColumn('status', function ($policy) {
                if ($policy->status == 1) {
                    $return = '<span class="kt-font-bold kt-font-brand">Activated</span>';
                } elseif ($policy->status == 2) {
                    $return = '<span class="kt-font-bold kt-font-danger">Cancel</span>';
                } else {
                    $return = '<span class="kt-font-bold kt-font-focus">Deactivated</span>';
                }
                if ($policy->kyc && $policy->kyc->compliance == 1) {
                    $return .= '<br><span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">KYC Compliant</span>';
                } else {
                    $return .= '<br><span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill">KYC Non-Compliant</span>';
                }
                return $return;
            })
            ->addColumn('name', function ($policy) {
                if ($policy->customer != null) {
                    return $policy->customer->firstName . ' ' . $policy->customer->lastName;
                }
            })
            ->addColumn('product_name', function ($policy) {
                if ($policy->product != null) {
                    return $policy->product->name;
                }
            })
            ->addColumn('actions', function ($policy) {
                $actions = '<a href="' . route('admin.policy.edit', $policy->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                if ($policy->status == 1 && $policy->kyc && $policy->kyc->compliance == 1) {
                    if ($policy->has_vehicle == 1 && $policy->has_member == 1) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', [$policy->id]) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass life accident claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($policy->has_vehicle == 1 && $policy->has_member == 0) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', [$policy->id]) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md glass accident claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    } elseif ($policy->has_vehicle == 0 && $policy->has_member == 1) {
                        $actions .= '<a href="' . route('admin.policy.processClaim', [$policy->id]) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md life claimTypeModal" title="Process Claim"><i class="la la-money"></i></a>';
                    }
                }
                return $actions;
            })
            ->rawColumns(['actions', 'status'])
            ->make(true);
    }

    public function viewPolicyManagement()
    {

        return view('admin/PolicyManagement/policyManagement');
    }

    public function getClaimData()
    {
        try {
            $claims = Claim::get(array('id', 'policy_id', 'claim_number', 'customer_id', 'claim_type', 'status', 'created_at'));
            return $claims;
        } catch (Exception $ex) {
            return response()->json(['data' => $ex]);
        }
    }

    public function makePolicy()
    {

        $policyPlan = PolicyPlan::where('status', 1)->get();

        //return response()->json($policyPlan);

        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();

        return view('admin/Policy/makePolicy', compact('carMake', 'policyPlan'));
    }

    public function makePolicyKamlesh()
    {

        $policyPlan = PolicyPlan::all();

        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();

        return view('Kamlesh/KamleshTest', compact('carMake', 'policyPlan'));
    }

    public function editPolicyView($id)
    {

        $policies = Policy::find($id);
        $policyPlan = PolicyPlan::all();
        $selectedPolicyPlan = PolicyPlan::find($policies->policyPlan);

        $userDetails = User::find($policies->user_id);
        $agentDetails = User::find($policies->agent_id);
        $customerKYC = KYC::where('user_id', $userDetails->id)->first();

        return view('admin/Policy/admin-editPolicy', compact('policies', 'policyPlan', 'selectedPolicyPlan', 'userDetails', 'customerKYC', 'agentDetails'));
    }

    public function createPolicy()
    {
        $policyPlan = PolicyPlan::all();
        return view('admin/Policy/createPolicy', compact('policyPlan'));
    }

    public function viewClaims()
    {
        return view('admin/Claims/adminClaims');
    }

    public function getAllPolicyPlans()
    {
        $policyPlan = PolicyPlan::all();
        return response()->json($policyPlan);
    }

    public function addPolicyView()
    {
        return view('admin/PolicyManagement/addNewPolicyManagement');
    }

    public function getAllPolicies(Request $request)
    {
        if ($request->ajax()) {
            $str = Input::get('search');
            //return response()->json($str);
            if ($str == "") {
                $policies = DB::table('policies')
                    ->leftJoin('vehicle', 'policies.id', '=', 'vehicle.policy_id')
                    ->leftJoin('users', 'policies.user_id', '=', 'users.id')
                    ->leftJoin('policy_plan', 'policies.plan_id', '=', 'policy_plan.id')
                    ->leftJoin('customer_kyc', 'policies.user_id', '=', 'customer_kyc.user_id')
                    ->select(
                        'policies.id as policyID',
                        'policies.policyNumber',
                        'policies.created_at as createdAt',
                        'policies.isActive',
                        'vehicle.vehiclePlate',
                        'vehicle.model',
                        'vehicle.make',
                        'users.id',
                        'users.firstName',
                        'users.lastName',
                        'policy_plan.plan as plan',
                        'users.cellphone',
                        'customer_kyc.compliance'
                    )->orderBy('policies.created_at', 'desc')
                    ->limit(500)
                    ->get();
                return response()->json($policies);
            } else {

                $policies = DB::table('policies')
                    ->leftJoin('vehicle', 'policies.id', '=', 'vehicle.policy_id')
                    ->leftJoin('users', 'policies.user_id', '=', 'users.id')
                    ->leftJoin('policy_plan', 'policies.plan_id', '=', 'policy_plan.id')
                    ->leftJoin('customer_kyc', 'policies.user_id', '=', 'customer_kyc.user_id')
                    ->select(
                        'policies.id as policyID',
                        'policies.policyNumber',
                        'policies.isActive',
                        'vehicle.vehiclePlate',
                        'vehicle.model',
                        'vehicle.make',
                        'users.id',
                        'users.firstName',
                        'users.lastName',
                        'policy_plan.plan as plan',
                        'users.cellphone',
                        'customer_kyc.compliance'
                    )
                    ->where('policyNumber', 'like', '%' . $str . '%')
                    ->orWhere('cellphone', 'like', '%' . $str . '%')
                    ->orWhere('vehiclePlate', 'like', '%' . $str . '%')
                    ->get();
                return response()->json('hey');
            }
        }
    }

    public function searchPolicies(Request $request)
    {

        $str = $request->search;

        switch ($request->category) {

            case 'PolicyNumber':
                $policies = DB::table('policies')
                    ->leftJoin('vehicle', 'policies.id', '=', 'vehicle.policy_id')
                    ->leftJoin('users', 'policies.user_id', '=', 'users.id')
                    ->leftJoin('policy_plan', 'policies.plan_id', '=', 'policy_plan.id')
                    ->select(
                        'policies.id as policyID',
                        'policies.policyNumber as policyNumber',
                        'policies.isActive',
                        'vehicle.vehiclePlate',
                        'vehicle.model',
                        'vehicle.make',
                        'users.id',
                        'users.firstName',
                        'users.lastName',
                        'policy_plan.plan as plan',
                        'users.cellphone'
                    )
                    ->where('policyNumber', 'like', '%' . $str . '%')
                    ->limit(100) // Limit results to prevent memory issues
                    ->get();

                return response()->json($policies);

                break;

            default:

                return response()->json('Break in switch case reached');

                break;
        }
    }

    public function policies()
    {

        return view('Admin/policies', compact('policies'));
    }

    public function viewAllCustomers()
    {

        return view('Admin/Customers/listCustomers');
    }

    public function getAllStaffMembers()
    {
        $staffMembers = Staff::all();
        return response()->json($staffMembers);
    }

    public function viewPolicyDetails($id)
    {
        $policies = Policy::find($id);
        $policyPlan = PolicyPlan::where('status', 1)->get();
        $selectedPolicyPlan = PolicyPlan::find($policies->plan_id)->first();
        $vehicle = Vehicle::find($policies->id);
        $glassClaims = GlassClaim::where('policy_id', $policies->id)->get();

        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();

        $userDetails = User::find($policies->user_id);
        $agentDetails = User::find($policies->agent_id);
        $customerKYC = KYC::where('user_id', $userDetails->id)->first();
        $customerBanking = CustomerBanking::where('policy_id', $policies->id)->first();

        $policyActivityLog = Activity::where('subject_id', $policies->id)->orderBy('created_at', 'desc')
            ->get();

        return view('Admin/Policy/policyDetails', compact('policies', 'userDetails', 'carMake', 'customerKYC', 'vehicle', 'agentDetails', 'policyPlan', 'selectedPolicyPlan', 'customerBanking', 'policyActivityLog', 'glassClaims'));
    }

    public function kamleshDetails($id)
    {
        $policies = Policy::find($id);
        $policyPlan = PolicyPlan::all();
        $selectedPolicyPlan = PolicyPlan::find($policies->plan_id);
        $vehicle = Vehicle::find($policies->id);

        $userDetails = User::find($policies->user_id);
        $agentDetails = User::find($policies->agent_id);
        $customerKYC = KYC::where('user_id', $userDetails->id)->first();
        $customerBanking = CustomerBanking::where('policy_id', $policies->id)->first();
        $carMake = DB::table('tb_prmotormakemodels')
            ->selectRaw('DISTINCT s_Make')
            ->get();
        //return response()->json($vehicle);

        return view('kam/Kamlesh/kamleshDetails', compact('carMake', 'policies', 'userDetails', 'customerKYC', 'vehicle', 'agentDetails', 'policyPlan', 'selectedPolicyPlan', 'customerBanking'));
    }

    public function listPolicies()
    {

        $policies = Policy::all();

        return view('Admin/Policy/addNewPolicy', compact('policies'));
    }

    public function addStaff()
    {

        $staffMembers = DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('role_id', 3)
            ->get();

        response()->json($staffMembers);

        return view('Admin/Staff/addNewStaff', compact('staffMembers'));
    }

    public function addCompanyView()
    {

        return view('Admin/Company/addNewCompany');
    }

    public function createTreaty(Request $request)
    {

        $treaty = new Treaty;
        $treaty->treatyName = $request->treatyName;
        $treaty->treatyNumber = $request->treatyNumber;
        $treaty->status = $request->status;
        $treaty->formula = $request->formula;
        $treaty->save();

        return response()->json('Treaty successfully created', 200);
    }

    public function activateUserPolicy(Request $request)
    {

        $policy = Policy::where('id', $request->policyId)->first();
        $userDetails = User::where('id', $policy->user_id)->first();
        $customerBanking = CustomerBanking::where('id', $policy->id)->first();

        $policy->isActive = 1;
        $policy->policyActivatedDate = Carbon::now()->toDateString();
        $policy->save();

        // $response = InfobipSms::send('+267' . $userDetails->cellphone, 'Dumelang ' . $userDetails->firstName . ', Alpha Direct has activated your ' . $request->policyNumber . ' policy ' . 'Your billing start date is :' . $request->billingStartDate . '. KYC:Complete');
        $response = event(new \AlphaDirect\Events\SendSms('+267' . $userDetails->cellphone, 'Dumelang ' . $userDetails->firstName . ', Alpha Direct has activated your ' . $request->policyNumber . ' policy ' . 'Your billing start date is :' . $request->billingStartDate . '. KYC:Complete'));
        $loggedInUser = Auth::user()->id;

        activity()
            ->performedOn($policy)
            ->causedBy($loggedInUser)
            ->log('Policy activated by: ' . Auth::user()->firstName . ' ' . Auth::user()->lastName);

        Session::flash('policyActivated', 'Policy has been Activated');
        return redirect()->back();
    }

    public function deactivateUserPolicy(Request $request)
    {

        $policy = Policy::where('id', $request->policyId)->first();
        $userDetails = User::where('id', $policy->user_id)->first();
        $customerBanking = CustomerBanking::where('id', $policy->id)->first();

        $policy->isActive = 0;
        $policy->save();

        $loggedInUser = Auth::user()->id;

        activity()
            ->performedOn($policy)
            ->causedBy($loggedInUser)
            ->log('Policy deactivated by: ' . Auth::user()->firstName . ' ' . Auth::user()->lastName);

        Session::flash('policyDeactivated', 'Policy has been dectivated');
        return redirect()->back();
    }
//
    public function deletePlan($id)
    {

        $policyPlan = PolicyPlan::find($id)->delete();

        Session::flash('policyPlanDeleted', 'Policy plan has been deleted');
        return redirect()->back();
    }

    public function activatePolicyPlan($id)
    {

        $policyPlan = PolicyPlan::where('id', $id)->first();

        $policyPlan->status = 1;
        $policyPlan->save();

        Session::flash('policyPlanActivated', 'Policy plan has been activated');

        return redirect()->back();
    }

    public function createPolicyPlan(Request $request)
    {

        $crearePolicyPlan = new PolicyPlan;

        $crearePolicyPlan->plan = 'P' . $request->premium . '/month for P' . number_format($request->sumInsured, 2) . ' insurance';
        $crearePolicyPlan->premium = $request->premium;
        $crearePolicyPlan->termType = $request->termType;
        $crearePolicyPlan->sumInsured = $request->sumInsured;

        if ($request->planStatus == 'Activated') {

            $crearePolicyPlan->status = 1;
        } else {

            $crearePolicyPlan->status = 0;
        }

        $crearePolicyPlan->save();

        Session::flash('policyPlanCreated', 'Policy plan has been created');

        return redirect()->back();
    }

    public function deactivatePolicyPlan($id)
    {

        $policyPlan = PolicyPlan::where('id', $id)->first();

        $policyPlan->status = 0;
        $policyPlan->save();

        Session::flash('policyPlanDeactivated', 'Policy plan has been deactivated');
        return redirect()->back();
    }
}
