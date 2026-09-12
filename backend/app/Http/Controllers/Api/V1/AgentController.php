<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Agency;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Services\CacheService;

/**
 * Agent & Agency management API.
 * Agents are users with an agency_id — they sell policies and earn commissions.
 */
class AgentController extends Controller
{
    // =========================================================================
    //  Agents (users who are agents)
    // =========================================================================

    public function agents(Request $request): JsonResponse
    {
        $query = DB::table('users as u')
            ->leftJoin('agencies as a', 'a.id', '=', 'u.agency_id')
            ->select([
                'u.id', 'u.firstName', 'u.lastName', 'u.email',
                'u.agency_id', 'a.name as agency_name', 'u.commission', 'u.active',
                'u.created_at',
            ]);

        // Filter by agency if provided
        if ($request->has('agency_id')) {
            $query->where('u.agency_id', $request->input('agency_id'));
        }

        // Search filter
        if ($request->has('search')) {
            $s = trim(preg_replace('/\s+/', ' ', $request->input('search')));
            $like = "%{$s}%";
            $words = count(explode(' ', $s)) >= 2
                ? array_values(array_filter(explode(' ', $s)))
                : [];
            $query->where(function ($q) use ($like, $words) {
                $q->where('u.firstName', 'like', $like)
                  ->orWhere('u.lastName', 'like', $like)
                  ->orWhereRaw("CONCAT_WS(' ', TRIM(u.firstName), TRIM(u.lastName)) LIKE ?", [$like])
                  ->orWhere('u.email', 'like', $like);
                if (count($words) >= 2) {
                    $q->orWhere(fn($i) =>
                        $i->where('u.firstName', 'like', "%{$words[0]}%")
                          ->where('u.lastName', 'like', "%{$words[1]}%")
                    )->orWhere(fn($i) =>
                        $i->where('u.firstName', 'like', "%{$words[1]}%")
                          ->where('u.lastName', 'like', "%{$words[0]}%")
                    );
                }
            });
        }

        $results = $query->orderBy('u.firstName')->simplePaginate($request->input('per_page', 25));

        // Batch-load policy counts + commission totals
        $agentIds = collect($results->items())->pluck('id')->toArray();
        $policyCounts = !empty($agentIds)
            ? DB::table('policies')->whereIn('agent_id', $agentIds)->where('status', 1)
                ->selectRaw('agent_id, COUNT(*) as cnt')->groupBy('agent_id')->pluck('cnt', 'agent_id')->toArray()
            : [];
        $commTotals = !empty($agentIds)
            ? DB::table('commission_ledger')->whereIn('agent_id', $agentIds)->where('entry_type', 'earned')
                ->selectRaw('agent_id, SUM(commission_amount) as total')->groupBy('agent_id')->pluck('total', 'agent_id')->toArray()
            : [];

        $fmt = fn($v) => $v !== null ? number_format((float) $v, 2, '.', ',') : '0.00';

        return response()->json([
            'data' => collect($results->items())->map(fn($u) => [
                'id'             => $u->id,
                'firstName'      => $u->firstName,
                'lastName'       => $u->lastName,
                'name'           => trim("{$u->firstName} {$u->lastName}"),
                'email'          => $u->email,
                'agencyId'       => $u->agency_id,
                'agencyName'     => $u->agency_name,
                'commission'     => $u->commission ? 'Yes' : 'No',
                'active'         => $u->active,
                'activePolicies' => $policyCounts[$u->id] ?? 0,
                'totalCommission'=> $fmt($commTotals[$u->id] ?? 0),
                'createdAt'      => $u->created_at,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    public function agentDetail(int $id): JsonResponse
    {
        $agent = \AlphaDirect\User::with(['agency', 'profile'])->find($id);

        if (!$agent) return response()->json(['message' => 'Agent not found.'], 404);

        // Performance stats
        $activePolicies = DB::table('policies')->where('agent_id', $id)->where('status', 1)->count();
        $totalPolicies  = DB::table('policies')->where('agent_id', $id)->count();
        $cancelledPolicies = DB::table('policies')->where('agent_id', $id)->where('status', 2)->count();

        $commissionEarned = DB::table('commission_ledger')
            ->where('agent_id', $id)->where('entry_type', 'earned')
            ->sum('commission_amount');
        $commissionPaid = DB::table('commission_ledger')
            ->where('agent_id', $id)->where('status', 'paid')
            ->sum('commission_amount');

        $retentionRate = $totalPolicies > 0
            ? round((($totalPolicies - $cancelledPolicies) / $totalPolicies) * 100, 1)
            : 0;

        $roles = $agent->getRoleNames()->toArray();
        $profile = $agent->profile;

        return response()->json([
            'data' => [
                'id'              => $agent->id,
                'firstName'       => $agent->firstName,
                'lastName'        => $agent->lastName,
                'name'            => trim("{$agent->firstName} {$agent->lastName}"),
                'email'           => $agent->email,
                'agencyId'        => $agent->agency_id,
                'agencyName'      => $agent->agency ? $agent->agency->name : null,
                'pin'             => $agent->pin ?? null,
                'commission'      => (bool) $agent->commission,
                'isGraphiteLogin' => (bool) $agent->is_graphite_login,
                'isReportLogin'   => (bool) $agent->is_report_login,
                'bypass500k'      => (bool) $agent->bypass_500k,
                'role'            => $roles,
                'departmentId'    => $profile ? $profile->department_id : null,
                'dob'             => $profile ? $profile->dob : null,
                'gender'          => $profile ? $profile->gender : null,
                'omang'           => $profile ? $profile->omang : null,
                'passport'        => $profile ? $profile->passport : null,
                'address'         => $profile ? $profile->address : null,
                'cellphone'       => $profile ? $profile->cellphone : null,
                'createdAt'       => $agent->created_at,
                'stats' => [
                    'activePolicies'    => $activePolicies,
                    'totalPolicies'     => $totalPolicies,
                    'cancelledPolicies' => $cancelledPolicies,
                    'retentionRate'     => $retentionRate,
                    'commissionEarned'  => number_format((float) $commissionEarned, 2, '.', ','),
                    'commissionPaid'    => number_format((float) $commissionPaid, 2, '.', ','),
                ],
            ],
        ]);
    }

    // =========================================================================
    //  Agencies
    // =========================================================================

    public function agencies(Request $request): JsonResponse
    {
        $query = DB::table('agencies')->orderBy('name');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $agencies = $query->get(['id', 'name', 'status', 'created_at']);

        // Batch-load agent counts and total premium per agency
        $agencyIds = $agencies->pluck('id')->toArray();
        $agentCounts = !empty($agencyIds)
            ? DB::table('users')->whereIn('agency_id', $agencyIds)
                ->selectRaw('agency_id, COUNT(*) as cnt')->groupBy('agency_id')->pluck('cnt', 'agency_id')->toArray()
            : [];
        $premiumTotals = !empty($agencyIds)
            ? DB::table('policies')->whereIn('agency_id', $agencyIds)->where('status', 1)
                ->selectRaw('agency_id, SUM(premium) as total')->groupBy('agency_id')->pluck('total', 'agency_id')->toArray()
            : [];

        $fmt = fn($v) => $v !== null ? number_format((float) $v, 2, '.', ',') : '0.00';

        return response()->json([
            'data' => $agencies->map(fn($a) => [
                'id'           => $a->id,
                'name'         => $a->name,
                'status'       => $a->status,
                'agentCount'   => $agentCounts[$a->id] ?? 0,
                'totalPremium' => $fmt($premiumTotals[$a->id] ?? 0),
                'createdAt'    => $a->created_at,
            ]),
        ]);
    }

    public function storeAgency(Request $request): JsonResponse
    {
        $request->merge(['name' => Agency::normalizeName($request->input('name'))]);
        $data = $request->validate(['name' => 'required|string|max:200|unique:agencies,name', 'status' => 'nullable|integer|in:0,1']);
        $id = DB::table('agencies')->insertGetId([
            'name' => $data['name'], 'status' => $data['status'] ?? 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->bustAgencyLookupCache();
        return response()->json(['message' => 'Agency created.', 'data' => ['id' => $id]], 201);
    }

    public function updateAgency(Request $request, int $id): JsonResponse
    {
        $request->merge(['name' => Agency::normalizeName($request->input('name'))]);
        $data = $request->validate(['name' => "required|string|max:200|unique:agencies,name,{$id}", 'status' => 'nullable|integer|in:0,1']);
        DB::table('agencies')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        $this->bustAgencyLookupCache();
        return response()->json(['message' => 'Agency updated.']);
    }

    /**
     * LookupController::agencies() caches the full A–Z list for an hour under
     * 'agencies_search_' . md5($search). Nothing cleared it on create/update, so
     * a new agency was missing from the Users / Agents / Policy dropdowns until
     * the TTL lapsed. forgetLookups() only flushes the tag on Redis; on the
     * file driver we must forget the exact unfiltered key too.
     */
    private function bustAgencyLookupCache(): void
    {
        Cache::forget('agencies_search_' . md5(''));
        CacheService::forgetLookups();
    }

    // =========================================================================
    //  Agent Create & Update
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|max:12',
            'confirmPassword' => 'required|string|same:password',
            'agency_id' => 'required|exists:agencies,id',
            'department_id' => 'nullable|exists:departments,id',
            'role' => 'nullable|array',
            'role.*' => 'string|exists:roles,name',
            'pin' => 'nullable|digits:4',
            'commission' => 'nullable|boolean',
            'is_graphite_login' => 'nullable|boolean',
            'is_report_login' => 'nullable|boolean',
            'bypass_500k' => 'nullable|boolean',
            'dob' => 'nullable|date',
            'gender' => 'required|string|in:Male,Female',
            'omang' => 'nullable|string|max:9',
            'passport' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'cellphone' => 'nullable|string|max:20',
        ]);

        \DB::beginTransaction();
        try {
            // Create the user
            $agent = \AlphaDirect\User::create([
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'agency_id' => $validated['agency_id'],
                'active' => 1,
                'commission' => $validated['commission'] ?? 0,
                'is_graphite_login' => $validated['is_graphite_login'] ?? 1,
                'is_report_login' => $validated['is_report_login'] ?? 0,
                'bypass_500k' => $validated['bypass_500k'] ?? 0,
                'is_first_login' => 1,
                'pin' => $validated['pin'] ?? null,
            ]);

            // Create user profile with additional fields
            \AlphaDirect\UserProfile::create([
                'user_id' => $agent->id,
                'department_id' => $validated['department_id'] ?? null,
                'dob' => $validated['dob'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'omang' => $validated['omang'] ?? null,
                'passport' => $validated['passport'] ?? null,
                'address' => $validated['address'] ?? null,
                'cellphone' => $validated['cellphone'] ?? null,
            ]);

            // Assign roles if provided
            if (!empty($validated['role'])) {
                $agent->syncRoles($validated['role']);
            }

            \DB::commit();

            return response()->json(['message' => 'Agent created successfully', 'data' => $agent], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Agent creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to create agent: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $agent = \AlphaDirect\User::findOrFail($id);

        $validated = $request->validate([
            'firstName' => 'string|max:100',
            'lastName' => 'string|max:100',
            'email' => ['email', \Illuminate\Validation\Rule::unique('users')->ignore($agent->id)],
            'agency_id' => 'exists:agencies,id',
            'department_id' => 'nullable|exists:departments,id',
            'pin' => 'nullable|digits:4',
            'commission' => 'nullable|boolean',
            'is_graphite_login' => 'nullable|boolean',
            'is_report_login' => 'nullable|boolean',
            'bypass_500k' => 'nullable|boolean',
            'dob' => 'nullable|date',
            // Present-but-empty must fail (the edit form sends gender: null when
            // nothing is selected); an absent key still allows partial PUTs.
            'gender' => 'sometimes|required|string|in:Male,Female',
            'omang' => 'nullable|string|max:9',
            'passport' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'cellphone' => 'nullable|string|max:20',
        ]);

        \DB::beginTransaction();
        try {
            // Update base user fields
            $userFields = array_intersect_key($validated, array_flip([
                'firstName', 'lastName', 'email', 'agency_id', 'pin',
                'commission', 'is_graphite_login', 'is_report_login', 'bypass_500k'
            ]));
            $agent->update($userFields);

            // Update or create user profile with profile fields
            $profileFields = array_intersect_key($validated, array_flip([
                'department_id', 'dob', 'gender', 'omang', 'passport', 'address', 'cellphone'
            ]));

            if (!empty($profileFields)) {
                $agent->profile()->updateOrCreate(
                    ['user_id' => $agent->id],
                    $profileFields
                );
            }

            \DB::commit();

            return response()->json(['message' => 'Agent updated successfully', 'data' => $agent]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Agent update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to update agent: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    //  Agent Logins
    // =========================================================================

    public function logins(Request $request): JsonResponse
    {
        $query = DB::table('agent_logins as al')
            ->join('users as u', 'u.id', '=', 'al.agent_id')
            ->select([
                'al.agent_id',
                DB::raw("CONCAT(u.firstName, ' ', u.lastName) as agent_name"),
                'al.store_id',
                'al.created_at as login_date',
                'al.last_activity',
            ])
            ->orderBy('al.created_at', 'desc');

        if ($request->filled('agent_id')) {
            $query->where('al.agent_id', $request->agent_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereRaw("CONCAT(u.firstName, ' ', u.lastName) LIKE ? OR u.email LIKE ?", ["%$search%", "%$search%"]);
        }

        return response()->json($query->paginate($request->per_page ?? 25));
    }
}
