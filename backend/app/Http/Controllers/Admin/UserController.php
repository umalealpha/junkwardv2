<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Agency;
use AlphaDirect\Claim;
use AlphaDirect\Department;
use AlphaDirect\Exports\MotorCompExport;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Role;
use AlphaDirect\Transaction;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use AlphaDirect\ClaimAssessment;
use AlphaDirect\UserRole;
use AlphaDirect\ClaimAccident;
use Carbon\Carbon;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\CustomerProfile;
use Hash;
use Log;
use Http\Client\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Redirect;
use Yajra\DataTables\DataTables;
use Session;
use Validator;
use AlphaDirect\Helper;
use AlphaDirect\Exports\UserExport;
use AlphaDirect\Models\RoleUnderRoles;
use Spatie\Permission\Models\Permission;
use AlphaDirect\Models\RoleHasPermissions;
use AlphaDirect\Models\CompanyName;

class UserController extends Controller
{
    /**
     * Show a list of all .
     *
     * @return View user listing
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('user-list'))
        {
            $roles = Role::select('id','name')->get();
            $agencys = Agency::where('status','1')->select('id','name')->get();
            return view("admin.user.index",compact('roles','agencys'));

        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function userExcelReport()
    {
        if (Auth::user()->hasPermissionTo('user-list'))
        {
            $roles = Role::select('id','name')->get();
            return view("admin.user.report_index",compact('roles'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    public function allUserDataJson(Request $request)
    {
        try
        {
            if(Auth::user()->hasRole('Manager')){
                $user = User::role('Agent')
                    ->get(array(
                        'id',
                        'firstName',
                        'lastName',
                        'email',
                        'active',
                    ));
            }elseif (Auth::user()->hasRole('Super Admin')){
                $user = User::with('roles')->get(array(
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'active',
                ));
            }else{
                if(Auth()->user()->can('access-all users')){
                    $user = User::with('roles')->get(array(
                        'id',
                        'firstName',
                        'lastName',
                        'email',
                        'active',
                    ));
                }else{
                $user = User::with('roles')->where('agency_id',Auth()->user()->agency_id)->get(array(
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'active',
                ));
            }
            }
            return DataTables::of($user)
                ->editColumn('active', function ($user)
                {
                    if ($user->active == null)
                    {
                        return 'Not-active';
                    }
                    elseif ($user->active == 1)
                    {
                        return 'Active';
                    }
                    elseif ($user->active == 2)
                    {
                        return 'Suspended';
                    }
                })->editColumn('role', function ($user)
                {
                    $role = $user->roles ? $user->getRoleNames() : 'No role Assigned';
                    $var = str_replace(array(
                        '"',
                        '[',
                        ']'
                    ) , '', $role);
                    return  $var;

                })
                ->make(false);
        }
        catch(\Exception $ex)
        {
            //throw $th;
            return response()->json($ex->getMessage());
        }

    }
    public function data(Request $request)
    {
        try
        {
			if(auth()->user()->can('access-all users') || Auth::user()->hasRole(['Super Admin','Agency Manager'])){
				$user = User::with('roles')->whereRaw('1=1');
			}
			if (auth()->user()->hasRole(['Agency Manager'])){
                $user = User::with('roles')->where('agency_id',Auth::user()->agency_id);
            }
            if (Auth::user()->hasPermissionTo('user-list'))
            {
                $user = User::with('roles')->whereRaw('1=1');
            }
            if(isset($request->Status_filter) && $request->Status_filter != '-1'){
                $user = $user->where('active',$request->Status_filter);
            }
            if(isset($request->role_filter) && $request->role_filter != '-1'){
                $user = $user->whereHas('roles', function ($query) use ($request) {
                    $query->where('id', $request->role_filter);
                });
            }
            if(isset($request->agency_filter) && $request->agency_filter != '-1'){
                $user = $user->where('agency_id',$request->agency_filter);
            }

            $search_arr = $request->get('search');

            $searchValue = trim($search_arr['value']); // Search value

            if ($searchValue != null) {
                $user->where('users.id', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.email', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.firstName', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.lastName', 'like', '%' . $searchValue . '%')
                    ->orWhere(DB::raw('CONCAT_WS(" ", users.firstName,users.lastName)'), 'like', '%' . $searchValue . '%');
                #->orWhere('customer.cellphone', 'like', '%' .$searchValue . '%');
            }

			$user = $user->get(array(
				'id',
				'agency_id',
				'firstName',
				'lastName',
				'email',
				'active',
				'commission',
				'created_at'
			));

			return DataTables::of($user)

                ->editColumn('commission',function($user) {
                    $commission = '';
                    if ($user->commission == 1) {
                        $commission = 'Yes';
                    }else{
                        $commission = 'No';
                    }
                    return $commission;
                })


                ->editColumn('email', function ($user)
                {
                    return $user->email;
                })->addColumn('names', function ($user)
                {
                    return $user->firstName . ' ' . $user->lastName;
                })->editColumn('active', function ($user)
                {
                    if ($user->active == null)
                    {
                        return '<span class="kt-badge  kt-badge--brand kt-badge--inline kt-badge--pill">Not-active</span>';
                    }
                    elseif ($user->active == 0)
                    {
                        return '<span class="kt-badge  kt-badge--brand kt-badge--inline kt-badge--pill">Not-active</span>';
                    }
                    elseif ($user->active == 1)
                    {
                        return '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Active</span>';
                    }
                    elseif ($user->active == 2)
                    {
                        return '<span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill">Suspended</span>';
                    }
                })->addColumn('role', function ($user)
                {
                    $role= '';
					foreach($user->roles as $r ){
						$role .='<span class="kt-badge  kt-badge--accent kt-badge--inline kt-badge--pill">' . $r->name . '</span>';
					}
                    return ($role=="") ? '<span class="kt-badge kt-badge--danger kt-badge--inline kt-badge--pill">No role Assigned</span>':$role;
                })->addColumn('agency_id', function ($user)
                {
                    if($user->agency_id != null){
                    $agency = Agency::where('id',$user->agency_id)->first(array('name'));
                        return $agency->name;
                    }else {
                        return '-';
                    }

                })->addColumn('actions', function ($user)
                {
                    $actions = '';
                   if($user->hasRole(['Super Admin','Admin']) && !Auth::user()->hasRole(['Super Admin','Admin'])){

                   }else{
                    if (auth::user()->hasPermissionTo('user-edit'))
                    {
                        $actions .= '<a href="' . route('admin.user.edit', $user->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                    }
                    else
                    {
                        $actions .= '<a href="' . route('admin.user.edit', $user->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                    }
                    if (auth::user()
                        ->can('user-delete'))
                    {
                        $actions .= '<a href="" value="' . $user->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                        <i class="la la-trash"></i>
                         </a>';
                    }
                   }
                    return $actions;
                })->rawColumns(['names', 'actions', 'active', 'role','agency_id'])
                ->make(true);
        }
        catch(\Exception $ex)
        {
            return response()->json($ex->getMessage());
        }

    }
    public function allReportUserDataJson(Request $request)
    {
        $user = array();
        try
        {

            if(Auth::user()->hasRole('Manager')){
                $user = User::role('Agent')
                    ->get(array(
                        'id',
                        'firstName',
                        'lastName',
                        'email',
                        'active',
                        'commission',
                    ));
            }elseif (Auth::user()->hasRole('Agency Manager')){
                $user = User::where('agency_id',Auth::user()->agency_id)
                    ->get(array(
                        'id',
                        'firstName',
                        'lastName',
                        'email',
                        'active',
                        'commission',
                    ));
            }else{
                $query = User::leftjoin('user_roles','users.id','user_roles.user_id')
                    ->leftjoin('user_profile','users.id','user_profile.user_id')
                    ->leftjoin('roles','user_roles.role_id','roles.id');
                if($request->roles_filter != -1){
                    $query->where('user_roles.role_id',$request->roles_filter);
                }
                if($request->status_filter != -1){
                    $query->where('users.active',$request->status_filter);
                }
                $user = $query->get(array(
                    'users.id',
                    'users.firstName',
                    'users.lastName','users.email',
                    'users.active',
                    'roles.name as user_role',
                    'user_profile.cellphone',
                    'users.commission',
                    'user_profile.address'
                ));
            }

            return DataTables::of($user)
                ->editColumn('agency_name', function ($user)
                {
                    return 'ALPHA DIRECT INSURANCE CO (HQ)';
                })
                ->editColumn('account_id', function ($user)
                {
                    return 'ALPHA';
                })

                ->editColumn('fax', function ($user)
                {
                    return '(267) 392-8265';
                })
                ->editColumn('principal', function ($user)
                {
                    return 'Arun Iyer';
                })
                ->editColumn('principal_email', function ($user)
                {
                    return 'aiyer@alphadirect.co.bw';
                })
                ->editColumn('product', function ($user)
                {
                    return 'Domestic All Risk';
                })
                ->editColumn('under_writer', function ($user)
                {
                    return 'Arun P. Iyer';
                })
                ->editColumn('service_rep', function ($user)
                {
                    return 'Arun P. Iyer';
                })

                ->editColumn('city', function ($user)
                {
                    return 'GABORONE';
                })
                ->editColumn('country', function ($user)
                {
                    return 'BOTSWANA';
                })
                ->editColumn('postal_code', function ($user)
                {
                    return 'GABORONE';
                })
                ->editColumn('s_LicenseNo', function ($user)
                {
                    return '2/9/179';
                })
              ->editColumn('active', function ($user)
                {
                    if ($user->active == null)
                    {
                        return 'Not-active';
                    }
                    elseif ($user->active == 1)
                    {
                        return 'Active';
                    }
                    elseif ($user->active == 2)
                    {
                        return 'Suspended';
                    }
                })

                ->rawColumns(['names', 'actions', 'active', 'role'])
                ->make(true);
        }
        catch(\Exception $ex)
        {
            //throw $th;
            return response()->json($ex->getMessage());
        }

    }

    /*
    * Pass user data through ajax call
    */
    /**
     * @return mixed
     */
    public function dataReport(Request $request)
    {
        $user = array();
        try
        {
            if(Auth::user()->hasRole('Manager')){
                $user = User::role('Agent')->get(array('id', 'firstName', 'lastName',
                        'email',
                        'active',
                        'created_at'
                    ));
            }else{
                $query = User::leftjoin('user_roles','users.id','user_roles.user_id')
                    ->leftjoin('user_profile','users.id','user_profile.user_id')
                    ->leftjoin('roles','user_roles.role_id','roles.id');
                if($request->roles_filter != -1){
                    $query->where('user_roles.role_id',$request->roles_filter);
                }
                if($request->status_filter != -1){
                    $query->where('users.active',$request->status_filter);
                }
                $user = $query->get(array('users.*','user_roles.role_id','roles.name as role_name','user_profile.cellphone','user_profile.address'));
            }

            return DataTables::of($user)
                ->editColumn('email', function ($user)
                {
                    return $user->email;
                })
                ->addColumn('agency_name', function ($user)
                {
                    return 'ALPHA DIRECT INSURANCE CO (HQ)';
                })
                ->addColumn('names', function ($user)
                {
                    return $user->firstName . ' ' . $user->lastName;
                })
                ->editColumn('account_id', function ($user)
                {
                    return 'ALPHA';
                })
                ->editColumn('cellphone', function ($user)
                {
                    return isset($user->cellphone)?$user->cellphone:'NA';
                })
                ->editColumn('fax', function ($user)
                {
                    return '(267) 392-8265';
                })
                ->editColumn('principal', function ($user)
                {
                    return 'Arun Iyer';
                })
                ->editColumn('principal_email', function ($user)
                {
                    return 'aiyer@alphadirect.co.bw';
                })
                ->editColumn('product', function ($user)
                {
                    return 'Domestic All Risk';
                })
                ->editColumn('under_writer', function ($user)
                {
                    return 'Arun P. Iyer';
                })
                ->editColumn('service_rep', function ($user)
                {
                    return 'Arun P. Iyer';
                })
                ->editColumn('s_AddressLine1', function ($user)
                {
                    return isset($user->address)?$user->address:'NA';
                })
                ->editColumn('AddressLine2', function ($user)
                {
                    return 'NA';
                })
                ->editColumn('AddressLine2', function ($user)
                {
                    return 'NA';
                })
                ->editColumn('city', function ($user)
                {
                    return 'GABORONE';
                })
                ->editColumn('country', function ($user)
                {
                    return 'BOTSWANA';
                })
                ->editColumn('postal_code', function ($user)
                {
                    return 'GABORONE';
                })
                ->editColumn('s_LicenseNo', function ($user)
                {
                    return '2/9/179';
                })
                ->editColumn('active', function ($user)
                {
                    if ($user->active == null)
                    {
                        return '<span class="kt-badge  kt-badge--brand kt-badge--inline kt-badge--pill">Not-active</span>';
                    }

                    elseif ($user->active == 1)
                    {
                        return '<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Active</span>';
                    }
                    elseif ($user->active == 2)
                    {
                        return '<span class="kt-badge  kt-badge--warning kt-badge--inline kt-badge--pill">Suspended</span>';
                    }
                })->addColumn('role', function ($user)
                {
                    $var = isset($user->role_name)?$user->role_name:'NA';
                    return '<span class="kt-badge  kt-badge--accent kt-badge--inline kt-badge--pill">' . $var . '</span>';
                })->addColumn('actions', function ($user)
                {
                    $actions = '';
                    if (auth::user()->hasPermissionTo('user-edit'))
                    {
                        $actions .= '<a href="' . route('admin.user.edit', $user->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </a>';
                    }
                    else
                    {
                        $actions .= '<a href="' . route('admin.user.edit', $user->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="flaticon-eye"></i>
                            </a>';
                    }
                    if (auth::user()
                        ->can('user-delete'))
                    {
                        $actions .= '<a href="" value="' . $user->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                        <i class="la la-trash"></i>
                         </a>';
                    }

                    return $actions;
                })->rawColumns(['names', 'actions', 'active', 'role'])
                ->make(true);
        }
        catch(\Exception $ex)
        {
            //throw $th;
            return response()->json($ex->getMessage());
        }

    }


    /**
     * Show a page to create page for user.
     *
     * @return View
     */
    public function create()
    {
        $roles2 = [];
        $data = Agency::getAgencies('status',1);
        $roleUnderRole = RoleUnderRoles::where('role_id',Auth()->user()->roles[0]->id)->first(['role_id','under_roles_ids']);
        if($roleUnderRole){
         $roles2 = json_decode($roleUnderRole->under_roles_ids);
        }
        if($data['Response'] == "success"){
            $agencies = $data['Agencies'];
        }else{
            return Redirect::back()->with('error', $data['Message']);
        }

        if (auth::user()
            ->hasPermissionTo('user-create'))
        {
            $depts = Department::get(array(
                'id',
                'name',
                'created_at'
            ));
            if($roles2 != []){
                $roles = Role::whereIn('id', $roles2)
                    ->orderBy('name', 'ASC')->get(array(
                        'id',
                        'name',
                        'created_at'
                    ));
            }else{
                $roles = Role::orderBy('name', 'ASC')->where('name','!=','Super Admin')->get(array(
                    'id',
                    'name',
                    'created_at'
                ));
            }
            /*
            if(Auth::user()->hasRole('Manager')){
                $roles = Role::whereIn('name', ['Agent', 'Accessor'])
                    ->get(array(
                        'id',
                        'name',
                        'created_at'
                    ));
            }else{
                $roles = Role::get(array(
                    'id',
                    'name',
                    'created_at'
                ));
            }
            */

            return view("admin.user.create", compact('depts', 'roles','agencies'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * method stores user data from create page
     *param: Request $request
     * @return user listing page
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try
        {

            $validator = Validator::make($request->all() , ['first_name' => 'required', 'last_name' => 'required', //check if email has not already been taken
            'password' => 'required|min:6|max:12|same:confirmPass', 'confirmPass' => 'required|same:password', 'role' => 'required', ]);

            // $validator = Validator::make($request->all() , ['first_name' => 'required', 'last_name' => 'required', 'email' => 'required|email|max:255|unique:users,email', //check if email has not already been taken
            //     'password' => 'required|min:6|max:12|same:confirmPass', 'confirmPass' => 'required|same:password', 'role' => 'required', ]);

            if ($validator->fails())
            {
                return Redirect::back()
                    ->withErrors($validator)->withInput();
            }
            else
            {
                /*Add Records to Users table*/
                $user = new User();
                $user->firstName = $request->first_name;
                $user->lastName = $request->last_name;
                $user->email = $request->cutomer_email;
                $user->password = Hash::make($request->password);
                $user->active = 1;

                if(isset($request->graphite_login))
                    $user->is_graphite_login = $request->graphite_login;
                else
                    $user->is_graphite_login = 0;

                if(isset($request->report_login))
                    $user->is_report_login = $request->report_login;
                else
                    $user->is_report_login = 0;

                if(isset($request->bypass_500k))
                    $user->bypass_500k = $request->bypass_500k;
                else
                    $user->bypass_500k = 0;

                $user->is_first_login = 1; //ToDo: This should be set to 1 until user creates a new password
                $user->commission = $request->commission;
                $user->agency_id = $request->agency;
                $request->commission ? $user->commission = $request->commission : $user->commission = 0;
                $user->save();
				$user->syncRoles(request()->get('role'));

                /*Add Records to Users Profile table*/
                $userProfile = new UserProfile();
                $userProfile->user_id = $user->id;
                $userProfile->department_id = $request->dept;
                $userProfile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
                $userProfile->gender = $request->gender;
                $userProfile->omang = $request->omang;
                $userProfile->passport = $request->passport;
                $userProfile->address = $request->address;
                $userProfile->cellphone = $request->mobile;
                $userProfile->save();

                $role = Role::where('name',$user->roles[0]->name)->first();
                $update = UserRole::where('user_id',$user->id)->first();

                if($update == null)
                    $update = new UserRole();

                $update->user_id = $user->id;
                $update->role_id = $role->id;
                $update->save();

                DB::commit();
                activity('Create')
                    ->performedOn($user)->causedBy(User::where('id', auth()
                        ->user()
                        ->id)
                        ->first())
                    ->log('User has been created');
                return Redirect::route('admin.user.index')
                    ->with('success', 'User Created Successfully');

            }

        }
        catch(\Exception $e)
        {
            DB::rollBack();
            return Redirect::route('admin.user.index')->with('error', $e->getMessage());
        }
    }

    /**
     * method provides edit page
     *param: user id ($id)
     * @return user listing page
     */
    public function edit($id)
    {

         $user = User::with('roles')->where('id', $id)->first();
        if($user->hasRole(['Super Admin','Admin']) && !Auth::user()->hasRole(['Super Admin','Admin'])){
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }else{
        if (auth::user()->hasPermissionTo('user-edit'))
        {
        $roles2 = [];
        $roleUnderRole = RoleUnderRoles::where('role_id',Auth()->user()->roles[0]->id)->first(['role_id','under_roles_ids']);
        if($roleUnderRole){
        $roles2 = json_decode($roleUnderRole->under_roles_ids);
        }
        $agency = Agency::get(array('id','name','status'));
        if(auth()->user()->active != 1)
            return redirect('/logout')->with('message', 'User is not active');

        if(auth()->user()->id == $id){
            $depts = Department::get(array(
                'id',
                'name',
                'created_at'
            ));
            //$roles = Role::get();

            if(Auth::user()->hasRole('Manager')){
                $roles = Role::whereIn('name', ['Agent', 'Accessor'])
                    ->get(array(
                        'id',
                        'name',
                        'created_at'
                    ));
            }else{
                $roles = Role::get(array(
                    'id',
                    'name',
                    'created_at'
                ));
            }

            $user = User::where('id', $id)->first();
			$userRole = $user->roles()->pluck('name')->toArray();



            $userProfile = UserProfile::where('user_id', $id)->first();
            return view('admin.user.edit', compact('depts', 'roles', 'user', 'userProfile', 'userRole','agency'));
        }

            $depts = Department::get(array(
                'id',
                'name',
                'created_at'
            ));
            //$roles = Role::get();

           /* if(Auth::user()->hasRole('Manager')){
                $roles = Role::whereIn('name', ['Agent', 'Accessor'])
                    ->get(array(
                        'id',
                        'name',
                        'created_at'
                    ));
            }elseif(Auth::user()->hasRole('Super Admin')){
                $roles = Role::get(array(
                    'id',
                    'name',
                    'created_at'
                ));
            }elseif(auth::user()->hasPermissionTo('user-update_role')){
                $roles = Role::whereNotIn('name', ['Super Admin'])
                        ->get(array(
                            'id',
                            'name',
                            'created_at'
                        ));
            }else{
                $roles = Role::whereNotIn('name', ['Super Admin'])
                        ->get(array(
                            'id',
                            'name',
                            'created_at'
                        ));
            }
*/

            if($roles2 != []){
                $roles = Role::whereIn('id', $roles2)
                    ->orderBy('name', 'ASC')->get(array(
                        'id',
                        'name',
                        'created_at'
                    ));
            }else{
                $roles = Role::orderBy('name', 'ASC')->where('name','!=','Super Admin')->get(array(
                    'id',
                    'name',
                    'created_at'
                ));
            }

            $user = User::where('id', $id)->first();
            $userRole =  $user->roles()->pluck('name')->toArray();

            $userProfile = UserProfile::where('user_id', $id)->first();
            $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", Auth::user()->roles[0]->id)
            ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
            ->all();
            // $rolePermissions = RoleHasPermissions::where("role_has_permissions.role_id", Auth::user()->roles[0]->id)
            // ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
            // ->all();

            $userpermissions = [];
            $category = [];
            if (!empty($rolePermissions))
            {
                foreach ($rolePermissions as $v)
                {
                    $permissionName = \Spatie\Permission\Models\Permission::where('id', $v)->first();

                    if( DB::table("model_has_permissions")->where("model_id",$user->id)->where("model_type","AlphaDirect\User")->where("permission_id",$permissionName->id)->exists()){
                        $checked = "checked";
                    }else{
                    $checked = null;
                    }

                    if($permissionName && $permissionName->name){
                    $userpermissions[] = array("id"=> $permissionName->id,"name"=> $permissionName->name, "checked" => $checked ,"category"=> $permissionName->category);

                    }
                }
            }

            $user_role_id =  $user->roles()->pluck('id');

            $role_permissions = DB::table("role_has_permissions")->whereIn("role_has_permissions.role_id", $user_role_id)->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
            ->all();

            $permission = Permission::orderBy('category', 'asc')->get();
            $company = CompanyName::all();
            if (auth::user()
                ->hasPermissionTo('user-edit'))
            {
                return view('admin.user.edit', compact('company','depts','roles', 'user', 'userProfile', 'userRole','agency','userpermissions','rolePermissions','category','permission','role_permissions'));
            }
            elseif (auth::user()
                ->hasPermissionTo('user-list'))
            {
                return view('admin.user.view', compact('depts', 'roles', 'user', 'userProfile', 'userRole','agency'));

            }
            else
            {
                return Redirect::back()
                    ->with('error', 'Sorry! You do not have permission to access this page!');
            }
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
       }
    }

    /**
     * method updates user account data from edit page
     *param: user id ($id)
     * @return user listing page
     */
    public function update($id, Request $request)
    {
		if(auth()->user()->active != 1){
			return redirect('/logout');
		}
		DB::beginTransaction();
		try
        {
            $flag =0;
            $user = User::findOrFail($id);

            if (Auth::check() && Auth::user()->roles->isNotEmpty() && Auth::user()->roles->first()->name == "Super Admin") {
               
                DB::table("model_has_permissions")
                    ->where("model_type", User::class)
                    ->where("model_id", $user->id)
                    ->delete();
                  
                // Assign the new permissions
                foreach ($request->UserPermissins as $permissionId) {
                    // Retrieve the permission instance
                    $permission = Permission::findOrFail($permissionId);
                    $user->givePermissionTo($permission);
                }
            }
            
           

            if($user->agency_id != null){
                if($agency = $user->agency){
                    if($agency->status != 1)
                        return redirect()->back()->with('error', 'User agency is inactive, can not update user');
                }else{
                    return redirect()->back()->with('error', 'User agency is not present. Please contact admin');
                }
            }
			$adminUsersCount = User::role('Super Admin') //get admin users with active status
			->where('active',1)
			->get()
			->count();
           // if(Auth::user()->hasRole('Super Admin')){
           //     $user->agency_id = request()->get('agency');
			//	if ($adminUsersCount > 1){
					$user->active = request()->get('active');
			//	}
           // }else{
			//	$user->active = request()->get('active');
			//}
           if (auth::user()
                ->hasPermissionTo('user-agencies_update')){
                $user->agency_id = request()->get('agency');
                if ($adminUsersCount > 1){
                    $user->active = request()->get('active');
                }
            }else{
                $user->active = request()->get('active');
            }
			$user->syncRoles(request()->get('role'));

            $user->firstName = $request->first_name;
            $user->lastName = $request->last_name;
            $user->email = $request->cutomer_email;
            $request->commission ? $user->commission = $request->commission : $user->commission = 0;

            if(isset($request->graphite_login))
                $user->is_graphite_login = $request->graphite_login;
            else
                $user->is_graphite_login = 0;

            if(isset($request->report_login))
                $user->is_report_login = $request->report_login;
            else
                $user->is_report_login = 0;

            if(isset($request->bypass_500k))
                $user->bypass_500k = $request->bypass_500k;
            else
                $user->bypass_500k = 0;

            if(isset($request->create_high_risk_quote))
                $user->create_high_risk_quote = $request->create_high_risk_quote;
            else
                $user->create_high_risk_quote = 0;

            // $user->commission = $request->commission;
            $user->company_id = isset($request->company_id) && $request->company_id != null ? $request->company_id :null;
            

            $user->save();
            $userProfile = UserProfile::where('user_id', $id)->first();
            $userProfile->user_id = $user->id;
            $userProfile->department_id = $request->dept;
            $userProfile->dob = Carbon::parse($request->get('dob'))->format('Y-m-d');
            $userProfile->gender = $request->gender;
            $userProfile->omang = $request->omang;
            $userProfile->passport = $request->passport;
            $userProfile->address = $request->address;
            $userProfile->cellphone = $request->cellphone;

            // WhatsApp AI access control
            if ($request->has('whatsapp_access')) {
                $userProfile->whatsapp_access = in_array($request->whatsapp_access, ['YES', 'NO'])
                    ? $request->whatsapp_access : null;
            }
            if ($request->has('whatsapp_visibility')) {
                $allowed = ['Admin', 'Full', 'Only department', 'Self'];
                $userProfile->whatsapp_visibility = in_array($request->whatsapp_visibility, $allowed)
                    ? $request->whatsapp_visibility : null;
            }

            $userProfile->save();

            /* $role = Role::where('name',$user->roles[0]->name)->first();
            $update = UserRole::where('user_id',$user->id)->first();

            if($update == null)
                $update = new UserRole();

            $update->user_id = $user->id;
            $update->role_id = $role->id;
            $update->save(); */

            DB::commit();
            activity('Update')
                ->performedOn($user)->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('User has been updated');
            if($flag == 0){
                return Redirect::route('admin.user.index')
                    ->with('success', 'User Updated Successfully');
            }else{
                return Redirect::route('admin.user.index')
                    ->with('success', 'User Updated Successfully except role and status');
            }

        }
        catch(\Exception $e)
        {
            DB::rollBack();
            return Redirect::route('admin.user.index')->with('error', $e->getMessage());
        }

    }

    public function hardResetUserRoles(){
        try{
            $users = User::with('roles')->get();
            foreach($users as $key=>$user){
                $role = Role::where('name',$user->roles[0]->name)->first();
                $update = UserRole::where('user_id',$user->id)->first();

                if($update == null)
                    $update = new UserRole();

                $update->user_id = $user->id;
                $update->role_id = $role->id;
                $update->save();
            }
            return response()->json(['status' => 'success'],200);

        }catch(\Excetion $ex){
            return response()->json(['status' => 'failed'],401);

        }
    }

    /**
     * method updates from no/ existing profile picture
     *param: user id ($id)
     * @return json success for on susscessful add/update
     */
    public function updateProfilePicture(Request $request, $id)
    {
        try
        {
            $userProfile = UserProfile::where('user_id', $id)->first();

            $this->validate($request, ['profile_picture' => 'image|mimes:jpeg,bmp,png,gif,jpg', //checks the format/extension of the file
            ]);

            if ($request->hasFile('profile_picture'))
            {

                $file = $request->file('profile_picture');
                $name = $this->gen_uuid() . $file->getClientOriginalName();

                $filepath = 'public/users/' . $userProfile->user_id . '/profile/profile_picture/' . $name;

                if (Storage::disk('s3')->exists($filepath)) //check if file exists, if yes delete and overwrrite with new profile picture s3

                {
                    Storage::disk('s3')->delete($filepath); //delete current profile picture in s3

                }
                Storage::disk('s3')->put($filepath, file_get_contents($file) , 'public'); // store the new profile picture
                $userProfile->profile_photo = $filepath;
                $userProfile->save();

            }
            return redirect()
                ->back()
                ->with('success', 'Profile picture changed');
        }
        catch(\Exception $ex)
        {
            //throw $th;
            return redirect()->back()
                ->with('error', $ex->getMessage());
        }

    }

    /**
     * methods updates existing password
     *param: user id ($id)
     * @return json success for on susscessful update
     */
    public function updatePassword(Request $request, $id)
    {
        try
        {
            //code...
            if ($request->password != null)
            {
                $messages = ['password.min' => 'Password should at least be 6 characters long', 'password.max' => 'Password should not be 24 characters long', 'confirm_password.same' => 'Password and Confirm Password should match, please try again.', ];
                /** Server Validations for Form values  */
                $validator = Validator::make($request->all() , [

                    "password" => 'required|min:6|max:24|same:confirm_password', "confirm_password" => 'required|same:password',

                ], $messages);

                if ($validator->fails())
                {
                    return Redirect::back()
                        ->withErrors($validator)->withInput();
                }
                else
                {
                    $user = User::where('id', $id)->first();
                    $user->password = Hash::make($request->password);
                    $user->save();
                    return redirect()
                        ->back()
                        ->with('success', 'Password Updated Successfully');

                }

            }

        }
        catch(\Exception $ex)
        {
            //throw $th;
            return redirect()->back()
                ->with('error', $ex->getMessage());
        }
    }

    /**
     * modal data for user account suspensions
     *param: user id ($id)
     * @return modal data through json
     */
    public function getModalSuspendAccount(Request $request)
    {
        $body = 'Are you sure you want to suspend the User account ?';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }

    /**
     * checks user role and role its associations and then deletes if nothing found
     *param: user id ($id)
     * @return modal data through json
     */
    public function getModalDelete(Request $request)
    {
        $user = User::where('id', $request->get('id'))
            ->first(array(
                'id',
                'firstName',
                'lastName'
            ));
        $user_role = $user->getRoleNames();

        switch ($user_role[0])
        {
            case 'Super Admin':
                $adminUsersCount = User::role('Super Admin')
                    ->where('active',1) //get admin users with active status
                    ->get()
                    ->count();

                if ($adminUsersCount > 1)
                {
                    $body = 'Are you sure you want to delete the User account with Super Admin Role?';
                    return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
                }
                else
                {
                    $body = 'Can not delete this Super Admin account.';
                    return response()->json(['status' => 'error', 'body' => $body]);
                }
                break;

            case 'Agent':
                $check1 = Claim::where('agent_id', $user->id)
                    ->get()
                    ->count();
                if ($check1)
                {
                    $body = 'Agent assigned to Claim. Cannot delete this Agent';
                    return response()->json(['status' => 'error', 'body' => $body]);
                }
                else
                {
                    $body = 'Are you sure you want to delete the User account ?';
                    return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
                }
                break;

            case 'Attorney':
                $check2 = ClaimAccident::where('attorney_id', $user->id)
                    ->get()
                    ->count();
                if ($check2)
                {
                    $body = 'Attorney assigned to Claim. Cannot delete this Attorney';
                    return response()->json(['status' => 'error', 'body' => $body]);
                }
                else
                {
                    $body = 'Are you sure you want to delete the User account ?';
                    return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
                }
                break;

            case 'Accessor':
                $check3 = ClaimAssessment::where('assessor_id', $user->id)
                    ->get()
                    ->count();
                if ($check3)
                {
                    $body = 'Accessor assigned to Claim. Cannot delete this Accessor';
                    return response()->json(['status' => 'error', 'body' => $body]);
                }
                else
                {
                    $body = 'Are you sure you want to delete the User account ?';
                    return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
                }
                break;

            default:
                $body = 'Are you sure you want to delete the User account ?';
                return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
        }

    }

    /**
     *deletes specific user accounts
     *param: user id ($id)
     * @return user listing page
     */
    public function destroy($id)
    {
        try
        {

            User::where('id', $id)->delete();
            UserProfile::where('user_id', $id)->delete();
            UserRole::where('user_id', $id)->delete();
            activity('Delete')->performedOn(User::where('id', $id)->first())
                ->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('User has been deleted');


            return Redirect::route('admin.user.index')
                ->with('success', 'User has been deleted');
        }
        catch(Exception $e)
        {
            return Redirect::route('admin.user.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * suspends active/inactive user accounts
     *
     * @return user listing page
     */
    public function suspend($id)
    {
        try
        {
            activity('Suspend')->performedOn(User::where('id', $id)->first())
                ->causedBy(User::where('id', auth()
                    ->user()
                    ->id)
                    ->first())
                ->log('User has been suspended');

            /**Suspend Users instead of deleting of deleting their account */

            $user = User::findOrFail($id);
            $user->active = 2;
            $user->save();
            return Redirect::route('admin.user.index')
                ->with('success', 'User has been suspended');
        }
        catch(Exception $e)
        {
            return Redirect::route('admin.user.index')->with('error', 'Something Went Wrong');
        }
    }

    /**
     * activates accounts during first time log-in
     *
     * @return login page
     */
    public function updateFirstTimeAccount(Request $request, $id)
    {
        try
        {
            $messages = ['cellphone.regex' => 'Botswana cellphone number, do not include +267', 'password.min' => 'Password should at least be 6 characters long', 'password.max' => 'Password should not be 12 characters long', 'confirm_password.same' => 'Password and Confirm Password should match, please try again.', ];

            /** Server Validations for Form values  */
            $validator = Validator::make($request->all() , ["first_name" => 'required|string|min:2|max:30', "last_name" => 'required|string|min:2|max:30', "email" => 'required|email', "password" => 'required|min:6|max:12|same:confirm_password', "confirm_password" => 'required|same:password', "dob" => 'required|before:today', "cellphone" => 'required|regex:/^[7]{1}[0-9]{7}$/', "gender" => 'required',

            ], $messages);

            if ($validator->fails())
            {
                return Redirect::back()
                    ->withErrors($validator)->withInput();
            }
            else
            {
                $user            = User::findorFail($id);
                $user->firstName = $request->first_name;
                $user->lastName  = $request->last_name;
                $user->active    = '1';
                $user->email     = $request->email;
                $user->password  = Hash::make($request->password);
                if ($user->save())
                {
                    $userProfile = UserProfile::where('user_id', $id)->first();
                    $userProfile->address = $request->address;
                    $userProfile->omang = $request->omang;
                    $userProfile->cellphone = $request->cellphone;
                    $userProfile->gender = $request->gender;
                    $userProfile->dob = Carbon::parse($request->get('dob'))
                        ->format('Y-m-d');
                    $userProfile->save();
                }
                auth()
                    ->logout();
                $request->session()
                    ->flush(); //remove any session data
                return redirect()
                    ->route('login');
            }
        }
        catch(\Exception $ex)
        {
            //throw $th;
            return response()->json(['error' => $ex->getMessage() , 'line' => $ex->getLine() ]);
        }
    }

    /**
     * generates unique user id
     *
     * @return generated codes
     */
    private function gen_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            Helper::gen_ustring(0, 0xffff) , Helper::gen_ustring(0, 0xffff) ,

            // 16 bits for "time_mid"
            Helper::gen_ustring(0, 0xffff) ,

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            Helper::gen_ustring(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            Helper::gen_ustring(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            Helper::gen_ustring(0, 0xffff) , Helper::gen_ustring(0, 0xffff) , Helper::gen_ustring(0, 0xffff));
    }


    /**
     * restores user roles if user is not assigned with any role
     *
     * @return json
     */
    public function refreshRoles()
    {
        try
        {
            //code...
            $users = User::all(); // get a list of Users
            foreach ($users as $user)
            {
                # code...
                $user_has_role = UserRole::where('user_id', $user->id)
                    ->exists();

                if ($user_has_role == true)
                { //check if a the user(s) has a role in UserRole
                    $user_role_id = UserRole::where('user_id', $user->id)
                        ->first('role_id');

                    if ($user_role_id->role_id == 0)
                    {

                        $saveRole = new UserRole();
                        $saveRole->user_id = $user->id;
                        $saveRole->role_id = '8';
                        $saveRole->save();
                        // All current roles will be removed from the user and replaced by the array given
                        $user->syncRoles('Agent');

                        $user->assignRole('Agent'); //assign default Agent Role

                    }
                    else
                    {

                        $currentUserRoles = $user->getRoleNames(); //get all the user(s) roles ['Super Admin']
                        // All current roles will be removed from the user and replaced by the array given
                        $user->syncRoles($currentUserRoles);

                        $user->assignRole($currentUserRoles); //re-assign them their roles

                    }

                }
                else
                {

                    # code...
                    $saveRole = new UserRole();
                    $saveRole->user_id = $user->id;
                    $saveRole->role_id = '8';
                    $saveRole->save();
                    // All current roles will be removed from the user and replaced by the array given
                    $user->syncRoles('Agent');

                    $user->assignRole('Agent'); //assign default Agent Role

                }
            }

            return response()
                ->json(['message' => 'role list refreshed', 'status' => 'success'], 200);
        }
        catch(\Exception $ex)
        {
            //throw $th;
            return response()->json($ex->getMessage());
        }
    }

    /**
     * return count listings regarding specific user policy sale.
     *
     * @return view for user sales report
     */
    public function userSales()
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

        return view('admin.user.userSales', compact('policy', 'products', 'productPlans'));
    }

    /**
     * return json data for user sales
     *
     * @return json data
     */
    public function userPolicySalesData(Request $request)
    {
        try
        {
            //code...
            $query = DB::table('policies')->select('agent_id', DB::raw('count(agent_id) as total_agent_sale'))
                ->orderBy('total_agent_sale', 'DESC')
                ->join('users', 'policies.agent_id', '=', 'users.id');

            //filters
            if ($request->policyStatus_filter != - 1)
            {
                $query->where('status', $request->policyStatus_filter);
            }

            if ($request->product_plan != - 1)
            {
                $query->where('plan_id', $request->product_plan);
            }
            if ($request->product_filter != - 1)
            {
                $query->where('product_id', $request->product_filter);
            }
            if ($request->payment_status != - 1)
            {
                $paymentStatus = Transaction::where('status', 'like', '%' . $request->payment_status . '%')
                    ->first();
                $query->where('customer_id', $paymentStatus->customer_id);
            }

            if ($request->policy_number != - 1)
            {
                $query->where('policyNumber', $request->policy_number);
            }

            if ($request->filterDateFrom != '-1' && $request->filterDateto != '-1')
            {
                $query->whereBetween(DB::raw('date(policies.created_at)') , [Carbon::parse($request->filterDateFrom)
                    ->format('Y-m-d') , Carbon::parse($request->filterDateto)
                    ->format('Y-m-d') ]);
            }
            $policies = $query->groupBy('agent_id')
                ->get(); //get the data here
            return DataTables::of($policies)->addColumn('name', function ($policies)
            {
                $user = User::findorFail($policies->agent_id);
                return $user->firstName . ' ' . $user->lastName;
            })

                ->rawColumns(['name'])
                ->make(true);

            # code...

        }
        catch(\Exception $ex)
        {
            //throw $th;
            return response($ex->getMessage());
        }

    }

    /**
     * method checks for existing email id.
     *called from user create and edit page using ajax
     * @return json count (0 or 0+)
     */
    public function checkEmail(Request $request){
        try{
            $emailCount = User::where('email', $request->email)->count();
            return response()
                ->json(['count' => $emailCount]);

        }catch(\Exception $e){
            return response()
                ->json(['message' => $e->getMessage(), 'line' => $e->getLine()]);
        }

    }

    public function updateUserStatus($id,$status){
        try{
            $user = User::where('id',$id)->first(array('active'));
            $user->active = $status;
            $user->save();

            return true;
        }catch(\Exception $ex){
            return false;
        }
    }

    public function export(Request $request){
        return Excel::download(new UserExport($request->all()), 'AlphaDirectUsers.xlsx');
    }

    public function generatePassword($length = 8)
    {
        $numbers = substr(str_shuffle(str_repeat('0123456789', 5)), 0, 2);
        $lower = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', 5)), 0, 3);
        $upper = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNOPQRSTUVWXYZ', 5)), 0, 2);
        $special = substr(str_shuffle(str_repeat('!@#?*', 5)), 0, 2);

        $password = $numbers.$lower.$upper.$special;

        return substr(str_shuffle(str_repeat($password, 5)), 0, $length);
    }

    public function userPinIndex()
    {
        if (Auth::user()->hasPermissionTo('agent-pin-list'))
        {

            return view("admin.user.pinIndex");
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function userPinEdit($id)
    {
        if (Auth::user()->hasPermissionTo('agent-pin-list'))
        {
            $user = User::where('id', $id)->first();
            return view("admin.user.pinEdit", compact('user'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function userPinUpdate(Request $request)
    {


            $user = User::where('id', $request->id)->first();
            if($user){
                $user->pin = $request->pin;
                $user->save();
                return Redirect::back()->with('success', 'Agent pin update Successfully');

            }else{
                return Redirect::back()->with('error', 'Sorry! Agent not Found!');
            }


    }

    public function allUserDataPin()
    {

           // try
          //  {
                if (Auth::user()->hasPermissionTo('agent-pin-list'))
                {
                $user = User::where('active',1)->whereRaw('1=1');



                $user = $user->orderby('agency_id','desc')->get(array(
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'pin',
                    'agency_id'

                ));
                return DataTables::of($user)

                    ->editColumn('email', function ($user)
                    {
                        return $user->email;
                    })->addColumn('names', function ($user)
                    {
                        return $user->firstName . ' ' . $user->lastName;



                    })->addColumn('agency_name', function ($user)
                    {
                        if($user->agency_id != null){
                                $agency =   Agency::where('id',$user->agency_id)->where('status',1)->first();
                                if($agency){
                                    $agency_name = $agency->name;
                                }else{
                                    $agency_name = null;
                                }
                        }else{
                            $agency_name = null;
                        }
                        return $agency_name;



                    })->addColumn('actions', function ($user)
                    {
                        $actions = '';
                        if (auth::user()->hasPermissionTo('user-edit'))
                        {
                            $actions .= '<a href="' . route('admin.user.userPinEdit', $user->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                    <i class="la la-edit"></i>
                                </a>';
                        }



                        return $actions;
                    })->rawColumns(['names', 'actions','agency_name'])
                    ->make(true);

                }
          //  }
          //  catch(\Exception $ex)
          //  {
         //       return response()->json($ex->getMessage());
         //   }


    }
    public function randomUsePin()
    {
        $user = User::where('active',1)->where('pin',null)->get();
        if($user->count() > 0){
            foreach($user as $u)
            {
                $pin = rand ( 1000 , 9999);
                $u->pin =  $pin;
                $u->save();


            }
            return response()->json(['status' => "success"]);
        }else{
            return response()->json(['status' => "All Done"]);
        }


    }



}

