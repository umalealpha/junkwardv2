<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Api\V1\PartnerCompanyAdminController as Admin;
use AlphaDirect\Models\Partner\PartnerUser;
use AlphaDirect\Services\Partner\PartnerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * Partner portal auth + self-service for the customer SPA (start):
 *   POST public/partner/login          email + password → bearer token
 *   POST public/partner/set-password   from the emailed signed link
 *   GET  public/partner/me             (partner.auth) company, products, user
 *   POST public/partner/change-password(partner.auth)
 *   POST public/partner/logout         (partner.auth)
 *   GET  public/partner/policies       (partner.auth) own company's policies
 *   GET  public/partner/policies/{policyNumber}/documents (partner.auth) schedule + wording links
 */
class PublicPartnerAuthController extends Controller
{
    public function __construct(private PartnerAuthService $auth) {}

    /**
     * Partner password policy: ≥10 chars, letters + numbers, not in a known
     * breach corpus (HIBP k-anonymity range query — fails open if the API is
     * unreachable, so it never blocks a legitimate user on a network blip),
     * and must not contain the account's e-mail local part.
     */
    private function passwordRules(string $email): array
    {
        $local = mb_strtolower(explode('@', $email, 2)[0] ?? '');
        return [
            'required', 'string', 'max:200', 'confirmed',
            Password::min(10)->letters()->numbers()->uncompromised(),
            function ($attribute, $value, $fail) use ($local) {
                if ($local !== '' && mb_strlen($local) >= 4 && str_contains(mb_strtolower($value), $local)) {
                    $fail('The password must not contain your e-mail address.');
                }
            },
        ];
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:1', 'max:200'],
        ]);
        $r = $this->auth->login($data['email'], $data['password'], $request->ip());
        if (!$r['ok']) {
            return response()->json(['ok' => false, 'error' => $r['error'], 'message' => $r['message']], 200);
        }
        return response()->json([
            'ok'         => true,
            'token'      => $r['token'],
            'expires_in' => $r['expires_in'],
        ] + $this->meShape($r['user']));
    }

    public function setPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'     => ['required', 'string'],   // base64 from the link
            'expires'   => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ]);
        $user = $this->auth->resolveSetPasswordLink($data['email'], (int) $data['expires'], $data['signature']);
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'invalid_link', 'message' => 'This link is invalid or has expired. Ask Alpha Direct to resend your access email.'], 422);
        }
        $data += $request->validate(['password' => $this->passwordRules($user->email)]);
        $this->auth->setPassword($user, $data['password']);
        return response()->json(['ok' => true, 'message' => 'Password set. You can now sign in.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['ok' => true] + $this->meShape($this->partner($request)));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->partner($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => array_merge($this->passwordRules($user->email), ['different:current_password']),
        ]);
        if (!Hash::check($data['current_password'], (string) $user->password)) {
            return response()->json(['ok' => false, 'error' => 'invalid_current', 'message' => 'Current password is incorrect.'], 422);
        }
        $this->auth->setPassword($user, $data['password']); // revokes all sessions, incl. this one
        return response()->json(['ok' => true, 'message' => 'Password changed. Please sign in again.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->revokeToken($request->bearerToken());
        return response()->json(['ok' => true]);
    }

    /** Policies issued under the partner's company (any channel, any login). */
    public function policies(Request $request): JsonResponse
    {
        $user  = $this->partner($request);
        $code  = $user->company->company_code;
        $page  = max(1, (int) $request->query('page', 1));
        $per   = min(100, max(10, (int) $request->query('per_page', 25)));
        $q     = trim((string) $request->query('search', ''));

        $base = DB::connection('mysql_system')->table('atc_shipments')
            ->where('company_code', $code)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('policy_number', 'like', "%$q%")
                ->orWhere('sender_name', 'like', "%$q%")
                ->orWhere('receiver_name', 'like', "%$q%")
                ->orWhere('courier_waybill', 'like', "%$q%")));

        $total = (clone $base)->count();
        $rows  = $base->orderByDesc('id')->forPage($page, $per)->get();

        // Status + schedule flag come from the legacy policies row (0 pending, 1 active, ...).
        $polById = DB::table('policies')->whereIn('id', $rows->pluck('policy_id')->filter()->all())
            ->get(['id', 'status', 'policyDocument'])->keyBy('id');
        $statusById = $polById->map(fn ($p) => $p->status);

        $data = $rows->map(fn ($s) => [
            'id'               => $s->id,
            'policy_id'        => $s->policy_id,
            'policy_number'    => $s->policy_number,
            'channel'          => $s->channel,
            'payment_status'   => $s->payment_status,
            'policy_status'    => isset($statusById[$s->policy_id]) ? (int) $statusById[$s->policy_id] : null,
            'issued_by_email'  => $s->issued_by_email,
            'issued_by_name'   => $s->issued_by_name,
            'sender_name'      => $s->sender_name,
            'receiver_name'    => $s->receiver_name,
            'from_zone'        => $s->from_zone,
            'from_town'        => $s->from_town,
            'to_zone'          => $s->to_zone,
            'to_town'          => $s->to_town,
            'goods_category'   => $s->goods_category,
            'goods_description'=> $s->goods_description,
            'sum_insured'      => (float) ($s->sum_insured ?? 0),
            'premium'          => (float) ($s->premium ?? 0),
            'courier_waybill'  => $s->courier_waybill,
            'created_at'       => $s->created_at,
            // Documents: schedule is issued once the policy is paid/active;
            // wording is always available. FE fetches links on demand.
            'schedule_available' => $this->scheduleAvailable($s, $polById[$s->policy_id] ?? null),
            'schedule_ready'     => !empty(($polById[$s->policy_id] ?? null)?->policyDocument),
        ]);

        $summary = DB::connection('mysql_system')->table('atc_shipments')->where('company_code', $code)
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(premium),0) as premium, SUM(CASE WHEN payment_status='unpaid' THEN 1 ELSE 0 END) as unpaid")
            ->first();

        return response()->json([
            'ok'      => true,
            'data'    => $data->values(),
            'meta'    => ['page' => $page, 'per_page' => $per, 'total' => $total],
            'summary' => ['total' => (int) $summary->total, 'premium' => (float) $summary->premium, 'unpaid' => (int) $summary->unpaid],
        ]);
    }

    /**
     * All documents for one of the company's policies — the same set the
     * Graphite admin "Documents" tab shows, minus customer KYC (partner staff
     * are not the insured; DPA minimisation):
     *   policy_documents — generated PDFs (policy_documents table: schedule /
     *                      endorsement / cancellation note). The schedule is
     *                      generated on first request once the policy is paid.
     *   wording          — documents table, resolved plan → product → global
     *                      exactly like PolicyController::wordingDocuments.
     *   static           — the per-product wording PDF + complaint procedure
     *                      served from storage (streamed via the two partner
     *                      routes below, bearer required).
     *   attachments      — policy_attachments uploaded by Alpha Direct staff.
     */
    public function documents(Request $request, string $policyNumber): JsonResponse
    {
        [$ship, $policy, $err] = $this->companyPolicy($request, $policyNumber);
        if ($err) {
            return $err;
        }

        $scheduleAvailable = $this->scheduleAvailable($ship, $policy);
        $scheduleError = null;
        if ($scheduleAvailable && empty($policy->policyDocument)
            && !DB::table('policy_documents')->where('policy_id', $policy->id)->exists()) {
            try {
                (new DocumentController())->generatePolicyDocument($policy->id);
                $policy = DB::table('policies')->where('id', $policy->id)->first(['id', 'policyNumber', 'product_id', 'plan_id', 'status', 'policyDocument']);
            } catch (\Throwable $e) {
                $scheduleError = 'Schedule could not be generated right now. Please try again shortly.';
                Log::error('partner.schedule_generate_failed', ['policy' => $policyNumber, 'error' => $e->getMessage()]);
            }
        }

        // 1. Generated policy documents
        $policyDocs = collect();
        if ($scheduleAvailable) {
            $policyDocs = DB::table('policy_documents')->where('policy_id', $policy->id)->orderByDesc('id')->get()
                ->map(fn ($d) => [
                    'id'         => 'doc_' . $d->id,
                    'name'       => $d->file_name ?: ($d->is_cancellation_note ? 'Cancellation Note' : 'Policy Schedule / Certificate of Insurance'),
                    'type'       => $d->is_cancellation_note ? 'Cancellation' : 'Policy Document',
                    'url'        => Helper::getCloudFrontURL($d->doc_path),
                    'created_at' => $d->created_at,
                ])->filter(fn ($d) => $d['url'])->values();
            if ($policyDocs->isEmpty() && !empty($policy->policyDocument)) {
                $policyDocs->push(['id' => 'pol_doc', 'name' => 'Policy Schedule / Certificate of Insurance', 'type' => 'Policy Document', 'url' => Helper::getCloudFrontURL($policy->policyDocument), 'created_at' => null]);
            }
        }

        // 2. Wording documents (documents table): plan → product → global
        $global   = DB::table('documents')->where('product_id', -1)->where('status', 1)->pluck('id')->all();
        $planDocs = DB::table('documents')->where('product_id', (int) $policy->product_id)->where('status', 1)
            ->whereNotNull('plan_id')->where('plan_id', $policy->plan_id)->pluck('id')->all();
        $wordingRows = $planDocs
            ? DB::table('documents')->whereIn('id', array_unique(array_merge($planDocs, $global)))->where('status', 1)->get(['id', 'name', 'link'])
            : DB::table('documents')->where('status', 1)->where(fn ($q) => $q->where('product_id', (int) $policy->product_id)->orWhere('product_id', -1))->get(['id', 'name', 'link']);
        $wording = $wordingRows->filter(fn ($d) => $d->link)->map(fn ($d) => [
            'id'   => 'wrd_' . $d->id,
            'name' => str_replace('_', ' ', $d->name ?: 'Policy Wording'),
            'url'  => Helper::getCloudFrontURL($d->link),
        ])->values();

        // 3. Static PDFs (streamed with the partner bearer — not public URLs)
        $base = storage_path('app/CoverageWiseMultimark');
        $static = [];
        if (is_file($base . '/' . $this->staticWordingFile((int) $policy->product_id))) {
            $static[] = ['key' => 'policy-wording', 'name' => 'Policy Wording (standard)', 'path' => "/public/partner/policies/{$policy->policyNumber}/wording-pdf"];
        }
        if (is_file($base . '/Customer_Complaints_Procedure_July_2023.pdf')) {
            $static[] = ['key' => 'complaint-procedure', 'name' => 'Customer Complaints Procedure', 'path' => "/public/partner/policies/{$policy->policyNumber}/complaint-procedure-pdf"];
        }

        // 4. Staff-uploaded attachments
        $attachments = DB::table('policy_attachments')->where('policy_id', $policy->id)->orderByDesc('id')->get()
            ->flatMap(function ($a) {
                $paths = @unserialize($a->attachment ?? '', ['allowed_classes' => false]);
                if (!is_array($paths)) $paths = $a->attachment ? [$a->attachment] : [];
                return collect($paths)->map(fn ($p, $i) => [
                    'id'         => 'att_' . $a->id . ($i > 0 ? "_$i" : ''),
                    'name'       => $a->name ?: 'Attachment',
                    'type'       => $a->type ?: 'Documents',
                    'url'        => Helper::getCloudFrontURL($p),
                    'created_at' => $a->created_at,
                ]);
            })->values();

        return response()->json([
            'ok'                 => true,
            'policy_number'      => $policy->policyNumber,
            'schedule_available' => $scheduleAvailable,
            'schedule_error'     => $scheduleError,
            'policy_documents'   => $policyDocs,
            'wording'            => $wording,
            'static'             => $static,
            'attachments'        => $attachments,
        ]);
    }

    /** Stream the standard per-product wording PDF (bearer-gated, company-scoped). */
    public function wordingPdf(Request $request, string $policyNumber)
    {
        [, $policy, $err] = $this->companyPolicy($request, $policyNumber);
        if ($err) {
            return $err;
        }
        $path = storage_path('app/CoverageWiseMultimark/' . $this->staticWordingFile((int) $policy->product_id));
        if (!is_file($path)) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        return response()->file($path, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Policy_Wording_' . $policy->policyNumber . '.pdf"']);
    }

    /** Stream the customer complaints procedure PDF (bearer-gated, company-scoped). */
    public function complaintProcedurePdf(Request $request, string $policyNumber)
    {
        [, , $err] = $this->companyPolicy($request, $policyNumber);
        if ($err) {
            return $err;
        }
        $path = storage_path('app/CoverageWiseMultimark/Customer_Complaints_Procedure_July_2023.pdf');
        if (!is_file($path)) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        return response()->file($path, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Customer_Complaints_Procedure.pdf"']);
    }

    // ─── Internals ──────────────────────────────────────────────────────

    /**
     * Resolve a policy number to the caller's company. Scoped by company_code
     * FIRST so another company's policy number is a plain 404 (no existence leak).
     * @return array{0:?object,1:?object,2:?JsonResponse}
     */
    private function companyPolicy(Request $request, string $policyNumber): array
    {
        $user = $this->partner($request);
        $ship = DB::connection('mysql_system')->table('atc_shipments')
            ->where('company_code', $user->company->company_code)
            ->where('policy_number', $policyNumber)
            ->orderByDesc('id')->first();
        $policy = $ship ? DB::table('policies')->where('id', $ship->policy_id)->first(['id', 'policyNumber', 'product_id', 'plan_id', 'status', 'policyDocument']) : null;
        if (!$ship || !$policy) {
            return [null, null, response()->json(['ok' => false, 'error' => 'policy_not_found'], 404)];
        }
        return [$ship, $policy, null];
    }

    /** Same map as PolicyCreateController::downloadPolicyWording; product 25 falls to the general wording. */
    private function staticWordingFile(int $productId): string
    {
        $map = [1 => 'PERSONALACCIDENT.pdf', 2 => 'Motor_Section.pdf', 3 => 'Motor_Section.pdf', 9 => 'Stated_Benefits_Section.pdf', 12 => 'Stated_Benefits_Section.pdf', 13 => 'Stated_Benefits_Section.pdf'];
        return $map[$productId] ?? 'General_Exceptions_Conditions_Provision.pdf';
    }

    /** Schedule is issued only once the premium is paid (policy active or shipment paid/settled). */
    private function scheduleAvailable(object $ship, ?object $policy): bool
    {
        return in_array((string) $ship->payment_status, ['paid', 'settled'], true)
            || ((int) ($policy->status ?? 0) === 1);
    }

    private function partner(Request $request): PartnerUser
    {
        /** @var PartnerUser $u */
        $u = $request->attributes->get('partner_user');
        return $u;
    }

    private function meShape(PartnerUser $u): array
    {
        $u->loadMissing('company');
        $ids = array_map('intval', $u->company->products ?? []);
        return [
            'user'    => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'last_login_at' => optional($u->last_login_at)->toIso8601String()],
            'company' => [
                'id'           => $u->company->id,
                'company_code' => $u->company->company_code,
                'name'         => $u->company->name,
                'products'     => array_values(array_map(fn ($id) => ['id' => $id, 'name' => Admin::ASSIGNABLE_PRODUCTS[$id] ?? ('Product ' . $id)], $ids)),
            ],
        ];
    }
}
