<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\UserLoginLog;
use AlphaDirect\User;
use AlphaDirect\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereRaw("CONCAT(firstName, ' ', lastName) LIKE ?", ["%$search%"])
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // Filter by role
        if ($request->filled('role_id')) {
            $query->whereHas('roles', function ($q) {
                $q->where('role_id', request()->role_id);
            });
        }

        // Filter by agency
        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('active', $request->status);
        }

        // `pin` is selected so the Edit Staff modal can pre-fill the agent's
        // current PIN (the 4-digit code used for policy issuance) without an
        // extra round-trip. Visible to admins only — this endpoint is already
        // behind the staff-management gate.
        //
        // `last_login_at` now lives on `users` (added by the 2026-06-13
        // add_profile_columns_to_users_table migration and written on every
        // login/SSO by AuthController). It used to be dropped here because the
        // column only existed on `hr_users` and selecting it threw
        // SQLSTATE[42S22] for every admin opening the staff list. Re-add it,
        // guarded by hasColumn so a DB that hasn't run the migration still
        // can't crash — so the Last Login column actually populates.
        $columns = ['id', 'firstName', 'lastName', 'email', 'agency_id', 'pin', 'active', 'created_at'];
        if (Schema::hasColumn('users', 'last_login_at')) {
            $columns[] = 'last_login_at';
        }

        return response()->json($query
            ->select($columns)
            ->with('roles', 'agency')
            ->paginate($request->per_page ?? 15)
        );
    }

    /**
     * Recent auth activity (login / logout / failed / sso_blocked) for one
     * staff user — newest first — from the append-only user_login_logs trail.
     * Same gate as the rest of /users (admin staff-management surface).
     */
    public function loginActivity($id)
    {
        $user = User::findOrFail($id);

        $logs = UserLoginLog::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'event', 'ip_address', 'user_agent', 'created_at']);

        return response()->json(['data' => $logs]);
    }

    public function show($id)
    {
        $user = User::with('profile', 'roles', 'agency')->findOrFail($id);

        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'agency_id' => 'nullable|exists:agencies,id',
            'role_id' => 'nullable|exists:roles,id',
            // in:0,1,2 — matches update() and the Status dropdown, which offers
            // Suspended (2). The old `boolean` rule rejected 2, so a staff
            // member could be edited to Suspended but never created as one.
            'active' => 'nullable|integer|in:0,1,2',
            // Same 4-digit rule as update() / updatePin(). The create form
            // sends a PIN but store() used to ignore it entirely, so every new
            // agent silently came out with no PIN.
            'pin' => 'nullable|digits:4',
            'dob' => 'nullable|date',
            'omang' => 'nullable|string|max:20',
            'passport' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'cellphone' => 'nullable|string|max:20',
            'work_position' => 'nullable|string|max:100',
        ]);

        // One transaction: previously the users row was committed before the
        // profile insert and role attach ran, so a failure in either left a
        // half-built account behind — and the retry then failed on
        // "email already taken" while the operator was told nothing was saved.
        try {
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'firstName' => $validated['firstName'],
                    'lastName'  => $validated['lastName'],
                    'email'     => $validated['email'],
                    'password'  => Hash::make($validated['password']),
                    'agency_id' => $validated['agency_id'] ?? null,
                    'active'    => $validated['active'] ?? 1,
                    'pin'       => $validated['pin'] ?? null,
                ]);

                // Not in $fillable, so it has to be forced — same reasoning as
                // update(): leave it null and the 90-day password-expiry check
                // has no baseline to count from.
                $user->forceFill(['password_changed_at' => now()])->save();

                // dob / omang / passport / address live on user_profile, not on
                // users. store() used to hand them to User::create(), where mass
                // assignment silently dropped every one of them.
                UserProfile::create([
                    'user_id'       => $user->id,
                    'dob'           => $validated['dob'] ?? null,
                    'omang'         => $validated['omang'] ?? null,
                    'passport'      => $validated['passport'] ?? null,
                    'address'       => $validated['address'] ?? null,
                    'cellphone'     => $validated['cellphone'] ?? null,
                    'work_position' => $validated['work_position'] ?? null,
                ]);

                // Additive attach, not syncRoles() — update() abandoned
                // syncRoles deliberately (it wipes every other role) and this
                // path must not reintroduce it. Also avoids Spatie's
                // findById() guard lookup blowing up on a role whose
                // guard_name isn't 'web'.
                if (!empty($validated['role_id'])) {
                    DB::table('model_has_roles')->insert([
                        'role_id'    => $validated['role_id'],
                        'model_id'   => $user->id,
                        'model_type' => \AlphaDirect\User::class,
                    ]);
                }

                return $user;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->userWriteFailure($e, $validated['email']);
        }

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    /**
     * Turn a failed staff INSERT into something the operator and the log can
     * act on. The create form previously surfaced a bare "Failed to save user"
     * with nothing highlighted, because a 500 carries no `errors` payload for
     * the UI to attach to a field.
     */
    private function userWriteFailure(\Illuminate\Database\QueryException $e, string $email)
    {
        \Illuminate\Support\Facades\Log::error('UserController.store.db_error', [
            'email'    => $email,
            'sqlstate' => $e->errorInfo[0] ?? null,
            'code'     => $e->errorInfo[1] ?? null,
            'error'    => $e->getMessage(),
        ]);

        $driverCode = $e->errorInfo[1] ?? null;
        $message    = $e->getMessage();

        // 1364 = no default value, 1048 = column cannot be null. Both mean the
        // users/user_profile table wants a column this code never writes —
        // name it, so the fix is one migration away instead of a guessing game.
        if (in_array($driverCode, [1048, 1364], true) && preg_match("/'([^']+)'/", $message, $m)) {
            return response()->json([
                'message' => "Could not create the user: the database requires a value for '{$m[1]}'. "
                    . 'Report this to IT — it needs a schema default.',
            ], 500);
        }

        return response()->json([
            'message' => 'Could not create the user. The details have been logged for IT.',
        ], 500);
    }

    /**
     * Roles that may rotate another staff member's password from the Edit
     * Staff screen, in addition to anyone holding the `user-edit` permission.
     * Mirrors the frontend gate in UserListPage.tsx — keep the two in step.
     */
    private const PASSWORD_MANAGER_ROLES = ['Admin', 'admin', 'Super Admin'];

    /**
     * May the acting user set another staff member's password?
     *
     * `can()` (not hasPermissionTo()) so an environment where the `user-edit`
     * permission row was never seeded returns false instead of throwing
     * PermissionDoesNotExist and 500-ing the whole save.
     */
    private function canManageStaffPasswords(): bool
    {
        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        return $actor->hasAnyRole(self::PASSWORD_MANAGER_ROLES)
            || $actor->can('user-edit');
    }

    public function update($id, Request $request)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'firstName' => 'string|max:100',
            'lastName' => 'string|max:100',
            'email' => ['email', Rule::unique('users')->ignore($user->id)],
            'accountType' => 'string',
            'agency_id' => 'nullable|exists:agencies,id',
            'role_id' => 'nullable|exists:roles,id',
            'active' => 'integer|in:0,1,2',
            'dob' => 'nullable|date',
            'omang' => 'nullable|string|max:20',
            'passport' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'cellphone' => 'nullable|string|max:20',
            'work_position' => 'nullable|string|max:100',
            'commission' => 'nullable|boolean',
            'is_graphite_login' => 'nullable|boolean',
            'bypass_500k' => 'nullable|boolean',
            // Agent PIN: exactly 4 digits, same rule as the dedicated
            // updatePin() endpoint. Only present in the payload when the
            // admin actually typed a new PIN, so an absent key leaves the
            // existing PIN untouched.
            'pin' => 'nullable|digits:4',
            // Optional password rotation from the Edit Staff modal. Absent or
            // blank leaves the existing hash untouched. `min:8` + `confirmed`
            // is the app's standing policy (Actions\Fortify\
            // PasswordValidationRules → Fortify's Password rule: length 8, no
            // complexity flags set) — deliberately the same bar as store()
            // and resetPassword() rather than a stricter one invented here.
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Pull the password out of $validated BEFORE the mass update. `password`
        // is in User::$fillable, so leaving it in would persist the plaintext
        // straight to the column and lock the staff member out.
        $newPassword = $validated['password'] ?? null;
        unset($validated['password'], $validated['password_confirmation']);

        if ($newPassword === '') {
            $newPassword = null;
        }

        // Refuse before any write, so a rejected password change can't leave
        // the other fields half-saved.
        if ($newPassword !== null && ! $this->canManageStaffPasswords()) {
            return response()->json([
                'message' => 'You do not have permission to change staff passwords.',
            ], 403);
        }

        $user->update($validated);

        if ($newPassword !== null) {
            // forceFill: password_changed_at is not in $fillable, and it has to
            // move or the PasswordValidation middleware keeps counting from the
            // OLD change date and can bounce the user straight into the 90-day
            // expiry redirect on their first login with the new password.
            $user->forceFill([
                'password'            => Hash::make($newPassword),
                'password_changed_at' => now(),
            ])->save();
        }

        // Update profile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'cellphone' => $validated['cellphone'] ?? null,
                'work_position' => $validated['work_position'] ?? null,
            ]
        );

        // Role management: idempotent ATTACH only.
        // The previous behaviour was $user->syncRoles([$role_id]) which
        // dropped every other role the user held. That locked admins out
        // of the admin UI the moment they assigned themselves a Grade
        // role for validation-rules testing (their Super Admin / Admin /
        // Manager roles got wiped in one click).
        // For destructive role removal use DELETE /users/{id}/roles/{role_id}.
        if (isset($validated['role_id'])) {
            $alreadyHas = DB::table('model_has_roles')
                ->where('role_id', $validated['role_id'])
                ->where('model_id', $user->id)
                ->where('model_type', \AlphaDirect\User::class)
                ->exists();
            if (!$alreadyHas) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $validated['role_id'],
                    'model_id'   => $user->id,
                    'model_type' => \AlphaDirect\User::class,
                ]);
            }
        }

        // User has no $hidden, so a bare toJson() ships the bcrypt hash (and
        // remember_token) back to the browser. Strip them from this response.
        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->makeHidden(['password', 'remember_token']),
        ]);
    }

    public function toggleStatus($id, Request $request)
    {
        $user = User::findOrFail($id);
        $newStatus = $request->status ?? ($user->active ? 0 : 1);
        $user->update(['active' => $newStatus]);

        return response()->json(['message' => 'Status updated', 'user' => $user]);
    }

    public function resetPassword($id, Request $request)
    {
        // Same operation as the password field on Edit Staff, just a different
        // route — so it carries the same gate. Without this the permission
        // check in update() is decorative: anyone authenticated could POST
        // here instead.
        if (! $this->canManageStaffPasswords()) {
            return response()->json([
                'message' => 'You do not have permission to change staff passwords.',
            ], 403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user = User::findOrFail($id);
        // forceFill so password_changed_at moves with the password — see the
        // note in update() about the 90-day PasswordValidation middleware.
        $user->forceFill([
            'password'            => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function pinList(Request $request)
    {
        $query = User::where('active', 1)->select('id', 'firstName', 'lastName', 'email', 'pin', 'agency_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereRaw("CONCAT(firstName, ' ', lastName) LIKE ? OR email LIKE ?", ["%$search%", "%$search%"]);
        }

        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        return response()->json($query->with('agency')->paginate($request->per_page ?? 15));
    }

    public function updatePin($id, Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = User::findOrFail($id);
        $user->update(['pin' => $validated['pin']]);

        return response()->json(['message' => 'PIN updated', 'pin' => $user->pin]);
    }

    public function generatePins(Request $request)
    {
        $users = User::where('active', 1)->whereNull('pin')->get();
        $count = 0;

        foreach ($users as $user) {
            $user->update(['pin' => str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT)]);
            $count++;
        }

        return response()->json(['message' => "Generated PINs for $count users"]);
    }
}
