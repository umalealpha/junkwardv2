<?php
namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Redirect;
use Form;
use AlphaDirect\Models\RoleUnderRoles;
use AlphaDirect\Models\ValidationRuleGroupMaster;

class RolesController extends Controller
{
    /**
     * Display a listing of the Roles.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (Auth::user()->hasPermissionTo('role-list'))
        {
            return view('admin.roles.index'/* compact('roles')*/);
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function roles()
    {
        if (Auth::user()->hasPermissionTo('role-list'))
        {
            $roles = Role::orderBy('id', 'DESC')->get();
            return view('admin.roles.roles', compact('roles'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function rolesStoreUpdate(Request $request)
    {
       // dd($request->all());
        if (Auth::user()->hasPermissionTo('role-list'))
        {
            if(RoleUnderRoles::where('role_id',$request->Roles)->exists()){
            $roleUnderRole = RoleUnderRoles::where('role_id',$request->Roles)->first();

            }else{
             $roleUnderRole = new RoleUnderRoles();
             $roleUnderRole->role_id = $request->Roles;
            }
            $roleUnderRole->under_roles_ids = json_encode($request->roleName);
            $roleUnderRole->save();
            $roles = Role::orderBy('id', 'DESC')->get();
            return Redirect::back()
                ->with('success', 'Your Role updated');
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }
    public function roleUnderRolesData(Request $request)
    {
        $data = [];
        if (Auth::user()->hasPermissionTo('role-list'))
        {
          if( !RoleUnderRoles::where('role_id',$request->id)->exists()){
            $data = [];
          }else{
               $roleUnderRole = RoleUnderRoles::where('role_id',$request->id)->first();
               $data = json_decode($roleUnderRole->under_roles_ids);

          }
            $roles = Role::orderBy('id', 'DESC')->where('id','!=',$request->id)->where('id','!=',1)->get(['id','name']);
            return response()->json(['status' => 'success', 'id' => $request->id , 'data' => $data,'roles'=>$roles]);
          //  return view('admin.roles.roles', compact('roles'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /*
     * Pass data through ajax call
     */
    /**
     * @return mixed
     */
    public function data(Request $request)
    {
      //  $roles = Role::orderBy('id', 'DESC')->get(array('name','guard_name'));
        $roles = Role::select(['id','name','rule_group','guard_name'])->orderBy('id', 'DESC');
        return DataTables::of($roles)->editColumn('name', function ($roles)
        {
            return $roles->name;
        })->editColumn('rule_group', function ($roles)
        {
            if($roles->rule_group){
                $r_group = ValidationRuleGroupMaster::where('n_PrValidationRuleGroupMasters_PK',$roles->rule_group)->first(array('s_RuleCode','s_RuleDesc'));
                 if ($r_group != null) {
                     $name = $r_group->s_RuleCode . ' ' . $r_group->s_RuleDesc;
                 }else{
                     $name = '-';
                 }
             }else{
                 $name = '-';
             }
             return $name;
        })->addColumn('permission', function ($roles)
        {
            $permission = 'No Permission';
            $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $roles->id)
                ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
                ->all();
            if (!empty($rolePermissions))
            {
                $permission = '<ul>';
                foreach ($rolePermissions as $v)
                {
                    $permissionName = Permission::where('id', $v)->first();
                    if($permissionName && $permissionName->name)
                        $permission .= '<li>' . $permissionName->name . '</li>';
                }
                $permission .= '</ul>';
            }
            return $permission;
            })->addColumn('actions', function ($roles)
            {
                $actions = '';
                $actions .= '<a href="' . route('admin.roles.edit', $roles->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit">
                                <i class="la la-edit"></i>
                            </sa>';
                if (Auth::user()
                    ->hasPermissionTo('role-delete'))
                {
                    $actions .= '<a href="" value="' . $roles->id . '" class="btn btn-sm btn-clean btn-icon btn-icon-md confirm-delete" title="Delete">
                                <i class="la la-trash"></i>
                            </a>';
                }
                return $actions;
            })->rawColumns(['actions', 'permission'])
            ->make(true);
    }
    /**
     * Show the form for creating a new role.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::user()->hasPermissionTo('role-create'))
        {
            $permission = Permission::orderBy('category', 'asc')->get();
            $ruleGroups = ValidationRuleGroupMaster::get(['n_PrValidationRuleGroupMasters_PK','s_RuleCode','s_RuleDesc']);

            $category = null;
            return view('admin.roles.create', compact('permission', 'category','ruleGroups'));
        }
        else
        {
            return Redirect::back()->with('error', 'Sorry! You do not have permission to access this page!');
        }

    }

    /**
     * Store a newly created role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, ['name' => 'required|unique:roles,name', 'permission' => 'required','rule_group'=>'required']);

        $role = Role::create([
            'name' => $request->input('name'),
            'rule_group' => $request->rule_group
        ]);

        $role->syncPermissions($request->input('permission'));

        activity('Create')
            ->performedOn($role)->causedBy(User::where('id', Auth()
                ->user()
                ->id)
                ->first())
            ->log('New role created');
        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (Auth::user()->hasPermissionTo('role-edit'))
        {
            $category = null;
            $role = Role::find($id);
            $permission = Permission::orderBy('category', 'asc')->get();
            $ruleGroups = ValidationRuleGroupMaster::get(['n_PrValidationRuleGroupMasters_PK','s_RuleCode','s_RuleDesc']);

            $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $id)->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
                ->all();

            return view('admin.roles.edit', compact('role', 'permission', 'rolePermissions', 'category','ruleGroups'));
        }
        else
        {
            return Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, ['name' => 'required', 'permission' => 'required','rule_group'=>'required']);
        $role = Role::find($id);
        $role->name = $request->input('name');
        $role->rule_group = $request->rule_group;
        $role->save();

        $role->syncPermissions($request->input('permission'));

        activity('Update')
            ->performedOn($role)->causedBy(User::where('id', Auth()
                ->user()
                ->id)
                ->first())
            ->log('Role Updated');
        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role updated successfully');
    }

    public function getModalDelete(Request $request)
    {
        $body = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete Role</h5>' . '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>' . '</div>' . '<div class="modal-body">' . $body = '<p>Are you sure you want to delete the Role ? </p></div>';
        $body .= '<div class="modal-footer">' . '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';
        $body .= Form::open(['method' => 'DELETE', 'route' => ['admin.roles.destroy', $request->id], 'style' => 'display:inline']);
        $body .= Form::submit('Delete', ['class' => 'btn btn-danger']);
        $body .= Form::close();
        $body .= '</div>';
        return response()->json(['status' => 'success', 'id' => $request->get('id') , 'body' => $body]);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        activity('Delete')->performedOn(Role::where('id', $id)->first())
            ->causedBy(User::where('id', Auth()
                ->user()
                ->id)
                ->first())
            ->log('Role has been deleted');
        Role::where('id', $id)->delete();
        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted successfully');
    }
    public function getRolePermisions()
    {
        $permissions = Auth()->user()
            ->getAllPermissions();
        return $permissions;
    }
}

