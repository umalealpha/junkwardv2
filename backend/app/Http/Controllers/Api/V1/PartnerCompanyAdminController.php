<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\Partner\PartnerCompany;
use AlphaDirect\Models\Partner\PartnerUser;
use AlphaDirect\Services\Partner\PartnerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use OwenIt\Auditing\Models\Audit;

/**
 * Admin management of partner companies (couriers / retailers) and their
 * portal logins — the "staff management" equivalent for the embedded
 * B2B2C products. Routes: /api/v1/partner-companies/*, gated by
 * partner-company-list / partner-company-edit.
 *
 * Products a company may sell on start are stored as a JSON array of
 * product ids on atc_couriers.products (25 = Alpha Transit Cover).
 */
class PartnerCompanyAdminController extends Controller
{
    /** Products that can be assigned to a partner company. */
    public const ASSIGNABLE_PRODUCTS = [
        25 => 'Alpha Transit Cover (Goods-in-Transit)',
        // AlphaProtect device cover — add its product id when the product is seeded.
    ];

    public function __construct(private PartnerAuthService $auth) {}

    /**
     * Audit row for privileged actions that are not model attribute changes
     * (credential e-mail sent, sessions revoked). Same `audits` table the
     * Auditable trait writes to, so the trail is in one place.
     */
    private function auditEvent(Request $request, string $event, $model, array $data = []): void
    {
        try {
            Audit::create([
                'user_type'      => $request->user() ? get_class($request->user()) : null,
                'user_id'        => $request->user()?->id,
                'event'          => $event,
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->id,
                'old_values'     => [],
                'new_values'     => $data,
                'url'            => $request->fullUrl(),
                'ip_address'     => $request->ip(),
                'user_agent'     => substr((string) $request->userAgent(), 0, 1023),
                'tags'           => 'partner',
            ]);
        } catch (\Throwable $e) {
            Log::warning('partner.audit_write_failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $q = PartnerCompany::query()->withCount('users')->orderBy('name');
        if ($s = trim((string) $request->query('search', ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('company_code', 'like', "%$s%"));
        }
        $companies = $q->get()->map(fn ($c) => $this->companyRow($c));

        // Policy counts per company from atc_shipments (both channels).
        $counts = DB::connection('mysql_system')->table('atc_shipments')
            ->selectRaw('company_code, COUNT(*) as c, COALESCE(SUM(premium),0) as premium')
            ->groupBy('company_code')->get()->keyBy('company_code');
        $companies = $companies->map(function ($row) use ($counts) {
            $row['policy_count']  = (int) ($counts[$row['company_code']]->c ?? 0);
            $row['total_premium'] = (float) ($counts[$row['company_code']]->premium ?? 0);
            return $row;
        });

        return response()->json([
            'data'                => $companies->values(),
            'assignable_products' => collect(self::ASSIGNABLE_PRODUCTS)->map(fn ($n, $id) => ['id' => $id, 'name' => $n])->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $c = PartnerCompany::with(['users' => fn ($q) => $q->orderBy('name')])->findOrFail($id);
        return response()->json(['data' => $this->companyRow($c) + [
            'users' => $c->users->map(fn ($u) => $this->userRow($u))->values(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_code'  => ['required', 'string', 'max:16', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('mysql_system.atc_couriers', 'company_code')],
            'name'          => ['required', 'string', 'max:191'],
            'contact_name'  => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'agency_id'     => ['nullable', 'integer', 'exists:agencies,id'],
            'products'      => ['nullable', 'array'],
            'products.*'    => ['integer', Rule::in(array_keys(self::ASSIGNABLE_PRODUCTS))],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'status'        => ['nullable', 'boolean'],
        ]);
        $data['status'] = $data['status'] ?? true;

        // Auto-create the legacy agencies row the company's policies scope
        // under (same convention as AtcEventProcessor::courierAgencyId).
        if (empty($data['agency_id'])) {
            $agencyId = DB::table('agencies')->where('name', $data['name'])->value('id');
            if (!$agencyId) {
                $agencyId = DB::table('agencies')->insertGetId([
                    'name' => $data['name'], 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $data['agency_id'] = $agencyId;
        }

        $c = PartnerCompany::create($data);
        Log::info('partner_company.created', ['id' => $c->id, 'code' => $c->company_code, 'by' => $request->user()?->id]);
        return response()->json(['data' => $this->companyRow($c)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $c = PartnerCompany::findOrFail($id);
        $data = $request->validate([
            'name'          => ['sometimes', 'required', 'string', 'max:191'],
            'contact_name'  => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'agency_id'     => ['nullable', 'integer', 'exists:agencies,id'],
            'products'      => ['nullable', 'array'],
            'products.*'    => ['integer', Rule::in(array_keys(self::ASSIGNABLE_PRODUCTS))],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'status'        => ['nullable', 'boolean'],
        ]);
        $c->fill($data)->save();

        // Deactivating a company kills every open partner session.
        if (array_key_exists('status', $data) && !$data['status']) {
            foreach ($c->users()->pluck('id') as $uid) {
                $this->auth->revokeAllForUser((int) $uid);
            }
        }
        return response()->json(['data' => $this->companyRow($c->fresh())]);
    }

    // ─── Logins ─────────────────────────────────────────────────────────

    public function storeUser(Request $request, int $companyId): JsonResponse
    {
        $c = PartnerCompany::findOrFail($companyId);
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('mysql_system.partner_users', 'email')],
            'send_credentials' => ['nullable', 'boolean'],
        ]);

        $u = PartnerUser::create([
            'company_id'         => $c->id,
            'name'               => $data['name'],
            'email'              => mb_strtolower(trim($data['email'])),
            'password'           => null,
            'is_active'          => true,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $sent = false; $sendError = null;
        if ($data['send_credentials'] ?? true) {
            try {
                $this->auth->sendCredentials($u);
                $sent = true;
                $this->auditEvent($request, 'credentials_sent', $u, ['email' => $u->email, 'company_id' => $u->company_id, 'on_create' => true]);
            } catch (\Throwable $e) {
                $sendError = $e->getMessage();
                Log::error('partner_user.credentials_email_failed', ['id' => $u->id, 'error' => $sendError]);
            }
        }
        Log::info('partner_user.created', ['id' => $u->id, 'company' => $c->company_code, 'by' => $request->user()?->id]);

        return response()->json(['data' => $this->userRow($u), 'credentials_sent' => $sent, 'send_error' => $sendError], 201);
    }

    public function updateUser(Request $request, int $companyId, int $userId): JsonResponse
    {
        $u = PartnerUser::where('company_id', $companyId)->findOrFail($userId);
        $data = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $u->fill($data)->save();
        if (array_key_exists('is_active', $data) && !$data['is_active']) {
            $this->auth->revokeAllForUser($u->id);
        }
        return response()->json(['data' => $this->userRow($u->fresh())]);
    }

    /** (Re)send the set-password email. Also used as "reset password". */
    public function sendCredentials(Request $request, int $companyId, int $userId): JsonResponse
    {
        $u = PartnerUser::where('company_id', $companyId)->findOrFail($userId);
        if (!$u->is_active) {
            return response()->json(['ok' => false, 'message' => 'Login is inactive.'], 422);
        }
        try {
            $this->auth->sendCredentials($u);
        } catch (\Throwable $e) {
            Log::error('partner_user.credentials_email_failed', ['id' => $u->id, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Email failed: ' . $e->getMessage()], 500);
        }
        $this->auditEvent($request, 'credentials_sent', $u, ['email' => $u->email, 'company_id' => $u->company_id]);
        return response()->json(['ok' => true, 'message' => 'Access email sent to ' . $u->email]);
    }

    /** Force sign-out on every device. */
    public function revokeSessions(Request $request, int $companyId, int $userId): JsonResponse
    {
        $u = PartnerUser::where('company_id', $companyId)->findOrFail($userId);
        $this->auth->revokeAllForUser($u->id);
        $this->auditEvent($request, 'sessions_revoked', $u, ['email' => $u->email]);
        return response()->json(['ok' => true]);
    }

    // ─── Shapes ─────────────────────────────────────────────────────────

    private function companyRow(PartnerCompany $c): array
    {
        return [
            'id'            => $c->id,
            'company_code'  => $c->company_code,
            'name'          => $c->name,
            'contact_name'  => $c->contact_name,
            'contact_email' => $c->contact_email,
            'contact_phone' => $c->contact_phone,
            'agency_id'     => $c->agency_id,
            'products'      => array_map('intval', $c->products ?? []),
            'notes'         => $c->notes,
            'status'        => (bool) $c->status,
            'user_count'    => (int) ($c->users_count ?? $c->users()->count()),
            'created_at'    => optional($c->created_at)->toIso8601String(),
        ];
    }

    private function userRow(PartnerUser $u): array
    {
        return [
            'id'              => $u->id,
            'company_id'      => $u->company_id,
            'name'            => $u->name,
            'email'           => $u->email,
            'is_active'       => (bool) $u->is_active,
            'password_set'    => $u->password !== null,
            'password_set_at' => optional($u->password_set_at)->toIso8601String(),
            'last_login_at'   => optional($u->last_login_at)->toIso8601String(),
            'locked'          => $u->isLocked(),
            'created_at'      => optional($u->created_at)->toIso8601String(),
        ];
    }
}
