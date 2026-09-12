<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\AdGroupKycSubmission;
use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Models\EmployerGroupAuditLog;
use AlphaDirect\Models\EmployerGroupPolicy;
use AlphaDirect\Models\HrUser;
use AlphaDirect\Services\EmployerGroupCommsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployerGroupController extends Controller
{
    /** Shared validation rules for store()/update() (V8 field set + bulk_emails). */
    private const RULES = [
        'name'           => 'required|string|max:255',
        'industry'       => 'required|string|max:100',
        'other_industry' => 'nullable|string|max:100',
        'address'        => 'nullable|string|max:255',
        'town'           => 'nullable|string|max:100',
        'postal_code'    => 'nullable|string|max:20',
        'contact_name'   => 'required|string|max:255',
        'contact_phone'  => 'required|string|max:30',
        'contact_email'  => 'required|email|max:255',
        'no_of_employees'=> 'nullable|integer|min:1',
        'broker'         => 'nullable|string|max:255',
        'payment_method' => 'nullable|string|max:100',
        'notes'          => 'nullable|string|max:2000',
        'status'         => 'nullable|string|in:active,inactive,pending',
        // Array of addresses, or a comma/newline-delimited string — the
        // model mutator normalises either form to a JSON array.
        'bulk_emails'    => 'nullable',
        'bulk_emails.*'  => 'email',
    ];
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = EmployerGroup::query()
            // Legacy rows carry cased values ('Active', 'Suspended') while V2
            // writes lowercase — filter case-insensitively, no data migration.
            ->when($validated['status'] ?? null, fn($q, $v) => $q->whereRaw('LOWER(status) = ?', [strtolower($v)]))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('employer_group_id', 'like', "%{$search}%")
                      ->orWhere('contact_email', 'like', "%{$search}%")
                      ->orWhere('contact_phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $results->map(fn($g) => [
                'id' => $g->id,
                'employerGroupId' => $g->employer_group_id,
                'name' => $g->name,
                'industry' => $g->industry,
                'address' => $g->address,
                'contactName' => $g->contact_name,
                'contactPhone' => $g->contact_phone,
                'contactEmail' => $g->contact_email,
                'broker' => $g->broker,
                'paymentMethod' => $g->payment_method,
                'status' => $g->status,
                'noOfEmployees' => $g->no_of_employees,
                'createdAt' => optional($g->created_at)->toIso8601String(),
            ]),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->hasPermissionTo('employer-group-create')) {
            return response()->json(['message' => 'Forbidden. You do not have permission to create employer groups.'], 403);
        }

        $validated = $request->validate(self::RULES);

        $validated['employer_group_id'] = $this->generateUniqueCode();
        $validated['status'] = $validated['status'] ?? 'active';

        $group = EmployerGroup::create($validated);

        $actorName = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email;

        EmployerGroupAuditLog::record(
            'created',
            $group->employer_group_id,
            $user->id,
            $actorName,
            [
                'name'     => $group->name,
                'industry' => $group->industry,
                'contact'  => $group->contact_name,
                'email'    => $group->contact_email,
            ]
        );

        return response()->json([
            'message' => 'Employer group created successfully.',
            'data'    => [
                'id'              => $group->id,
                'employerGroupId' => $group->employer_group_id,
                'name'            => $group->name,
                'status'          => $group->status,
            ],
        ], 201);
    }

    private function generateUniqueCode(): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $length = rand(6, 10);
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
        } while (EmployerGroup::where('employer_group_id', $code)->exists());

        return $code;
    }

    /**
     * GET /api/v1/employer-groups/{id}
     * Full detail: all fields, HR emails + HR users, latest KYC submission
     * (directors/shareholders/documents), audit trail, signed document URLs.
     */
    public function show(int $id): JsonResponse
    {
        $group = EmployerGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Employer group not found'], 404);
        }

        $hrUsers = HrUser::where('employer_group_id', $group->id)
            ->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'email'       => $u->email,
                'name'        => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'isActive'    => (bool) $u->is_active,
                'hasPassword' => !empty($u->getAuthPassword()),
                'lastLoginAt' => $u->last_login_at,
                'createdAt'   => $u->created_at,
            ]);

        // KYC submission writers were inconsistent about the join key (short
        // code vs numeric PK) — match either, newest first.
        $submission = AdGroupKycSubmission::with(['directors', 'shareholders', 'documents'])
            ->where(function ($q) use ($group) {
                $q->where('employer_group_id', $group->employer_group_id)
                  ->orWhere('employer_group_id', (string) $group->id);
            })
            ->orderByDesc('id')
            ->first();

        $auditLogs = EmployerGroupAuditLog::where('employer_group_id', $group->employer_group_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id'        => $l->id,
                'event'     => $l->event,
                'actor'     => $l->actor,
                'details'   => $l->details,
                'createdAt' => $l->created_at,
            ]);

        return response()->json(['data' => [
            'id'              => $group->id,
            'employerGroupId' => $group->employer_group_id,
            'name'            => $group->name,
            'industry'        => $group->industry,
            'otherIndustry'   => $group->other_industry,
            'address'         => $group->address,
            'town'            => $group->town,
            'postalCode'      => $group->postal_code,
            'contactName'     => $group->contact_name,
            'contactPhone'    => $group->contact_phone,
            'contactEmail'    => $group->contact_email,
            'broker'          => $group->broker,
            'paymentMethod'   => $group->payment_method,
            'status'          => $group->status,
            'noOfEmployees'   => $group->no_of_employees,
            'hrEmails'        => $group->hr_emails,
            'accountName'     => $group->account_name,
            'accountNumber'   => $group->account_number,
            'bankNameBranch'  => $group->bank_name_branch,
            'notes'           => $group->notes,
            'createdAt'       => optional($group->created_at)->toIso8601String(),
            'updatedAt'       => optional($group->updated_at)->toIso8601String(),
            'documents'       => $this->groupDocuments($group),
            'hrUsers'         => $hrUsers,
            'kycSubmission'   => $submission ? [
                'id'           => $submission->id,
                'createdAt'    => $submission->created_at,
                'data'         => collect($submission->getAttributes())
                    ->except(['id', 'created_at', 'updated_at'])->all(),
                'directors'    => $submission->directors,
                'shareholders' => $submission->shareholders,
                'documents'    => $submission->documents,
            ] : null,
            'auditLogs'       => $auditLogs,
        ]]);
    }

    /**
     * PUT /api/v1/employer-groups/{id}
     * employer_group_id (the short code) is immutable — never accepted.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $group = EmployerGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Employer group not found'], 404);
        }

        // Update rules diverge from store: legacy rows carry cased statuses
        // ('Suspended', 'Cancelled') and null contact fields — the strict
        // store rules would 422 an unchanged edit-form save and silently
        // strand suspended groups. Normalize case, accept the full legacy
        // status vocabulary, and don't force contact data to be invented.
        if ($request->filled('status')) {
            $request->merge(['status' => strtolower((string) $request->input('status'))]);
        }
        $rules = self::RULES;
        $rules['status'] = 'nullable|string|in:active,inactive,pending,suspended,cancelled';
        $rules['contact_name'] = 'nullable|string|max:255';
        $rules['contact_phone'] = 'nullable|string|max:30';
        $rules['contact_email'] = 'nullable|email|max:255';
        $rules['no_of_employees'] = 'nullable|integer|min:0';

        $validated = $request->validate($rules);
        $before = $group->only(array_keys($validated));

        $group->update($validated);

        $user = Auth::user();
        $changed = collect($validated)
            ->filter(fn ($v, $k) => ($before[$k] ?? null) != $group->{$k})
            ->keys()->values()->all();

        EmployerGroupAuditLog::record(
            'updated',
            $group->employer_group_id,
            $user->id,
            trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email,
            ['changed_fields' => $changed]
        );

        return response()->json(['message' => 'Employer group updated successfully.']);
    }

    /**
     * DELETE /api/v1/employer-groups/{id}
     * Hard delete behind a dependency guard: 409 with counts when HR users,
     * group policies, or KYC campaigns still reference the group. (V8's
     * blind try/catch delete is intentionally not ported.)
     */
    public function destroy(int $id): JsonResponse
    {
        $group = EmployerGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Employer group not found'], 404);
        }

        $dependencies = array_filter([
            'hr_users'       => HrUser::where('employer_group_id', $group->id)->count(),
            'group_policies' => EmployerGroupPolicy::where('employer_group_id', $group->employer_group_id)->count(),
            'kyc_campaigns'  => DB::table('ad_group_kyc_campaigns')
                ->where('employer_group_id', $group->employer_group_id)->count(),
        ]);

        if (!empty($dependencies)) {
            return response()->json([
                'message' => 'Cannot delete: this employer group still has linked records.',
                'dependencies' => $dependencies,
            ], 409);
        }

        $user = Auth::user();
        EmployerGroupAuditLog::record(
            'deleted',
            $group->employer_group_id,
            $user->id,
            trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email,
            ['name' => $group->name]
        );

        $group->delete();

        return response()->json(['message' => 'Employer group deleted.']);
    }

    /**
     * POST /api/v1/employer-groups/{id}/send-hr-credentials
     */
    public function sendHrCredentials(int $id, EmployerGroupCommsService $comms): JsonResponse
    {
        $group = EmployerGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Employer group not found'], 404);
        }

        try {
            $result = $comms->sendHrCredentials($group);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = Auth::user();
        EmployerGroupAuditLog::record(
            'hr_credentials_sent',
            $group->employer_group_id,
            $user->id,
            trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email,
            [
                'sent'    => $result['sent_emails'],
                'failed'  => array_column($result['failed_emails'], 'email'),
                'created_hr_users' => $result['created_hr_users'],
            ]
        );

        $message = 'HR credentials sent to ' . count($result['sent_emails']) . ' email(s)';
        if (!empty($result['failed_emails'])) {
            $message .= ', failed for ' . count($result['failed_emails']);
        }

        return response()->json(['message' => $message, 'data' => $result]);
    }

    /**
     * POST /api/v1/employer-groups/{id}/send-onboarding-email
     */
    public function sendOnboardingEmail(int $id, EmployerGroupCommsService $comms): JsonResponse
    {
        $group = EmployerGroup::find($id);
        if (!$group) {
            return response()->json(['message' => 'Employer group not found'], 404);
        }

        try {
            $result = $comms->sendOnboardingEmail($group);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = Auth::user();
        EmployerGroupAuditLog::record(
            'onboarding_email_sent',
            $group->employer_group_id,
            $user->id,
            trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email,
            ['contact_email' => $result['contact_email']]
        );

        return response()->json([
            'message' => 'Onboarding email sent to ' . $result['contact_email'],
            'data' => $result,
        ]);
    }

    /**
     * Signed (10-min) URLs for the group's own uploaded documents. Missing
     * S3 objects or a misconfigured disk must not break the detail page.
     */
    private function groupDocuments(EmployerGroup $group): array
    {
        $docs = [];
        $files = [
            'certificate'     => [$group->certificate_file, $group->certificate_filename, 'Company Certificate'],
            'tax_certificate' => [$group->tax_certificate_file, $group->tax_certificate_filename, 'Tax Certificate'],
            'proof_address'   => [$group->proof_address_file, $group->proof_address_filename, 'Proof of Address'],
        ];
        foreach ($files as $key => [$path, $filename, $label]) {
            if (empty($path)) continue;
            try {
                $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));
            } catch (\Exception $e) {
                $url = null;
            }
            $docs[] = ['key' => $key, 'label' => $label, 'filename' => $filename, 'url' => $url];
        }
        return $docs;
    }

    public function policies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'employer_group_id' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('employer_group_policy as egp')
            ->join('policies as p', 'egp.policy_id', '=', 'p.id')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->leftJoin('employer_groups as eg', 'eg.employer_group_id', '=', 'egp.employer_group_id')
            ->select([
                'p.id', 'p.policyNumber', 'p.status', 'p.premium', 'p.created_at',
                'c.firstName', 'c.lastName', 'c.cellphone',
                'pr.name as product_name',
                'eg.name as group_name', 'egp.employer_group_id', 'egp.employee_id',
            ])
            ->when($validated['employer_group_id'] ?? null, fn($q, $v) => $q->where('egp.employer_group_id', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('p.policyNumber', 'like', $like)
                      ->orWhere('c.firstName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like])
                      ->orWhere('egp.employee_id', 'like', $like);
                    if (count($words) >= 2) {
                        $q->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[0]}%")
                              ->where('c.lastName', 'like', "%{$words[1]}%")
                        )->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[1]}%")
                              ->where('c.lastName', 'like', "%{$words[0]}%")
                        );
                    }
                });
            })
            ->orderBy('p.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'policyNumber' => $r->policyNumber,
                'status' => $r->status,
                'premium' => $r->premium,
                'customerName' => trim(($r->firstName ?? '') . ' ' . ($r->lastName ?? '')),
                'cellphone' => $r->cellphone,
                'productName' => $r->product_name,
                'groupName' => $r->group_name,
                'employerGroupId' => $r->employer_group_id,
                'employeeId' => $r->employee_id,
                'createdAt' => $r->created_at,
            ]),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }
}
