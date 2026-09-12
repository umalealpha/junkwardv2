<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DataAccessRequestController — DPO-gated reveal of masked customer PII.
 *
 *   POST   /api/v1/data-access                 store()    — raise a request
 *   GET    /api/v1/data-access/mine            mine()     — my requests
 *   GET    /api/v1/data-access/queue           queue()    — pending (approvers only)
 *   POST   /api/v1/data-access/{id}/decide     decide()   — approve|deny (approvers only)
 *   GET    /api/v1/data-access/{id}/reveal     reveal()   — unmasked fields (approved only)
 *
 * Approver gate = Spatie permission `customer-data.approve`
 * (Super Admin / Admin / Underwriting Head / Auditor; Compliance &
 * Finance Manager to be added once their roles are confirmed).
 */
class DataAccessRequestController extends Controller
{
    private const FIELDS = ['name', 'phone', 'address', 'bank', 'id'];
    private const MIN_WORDS = 50;

    private function canApprove(Request $request): bool
    {
        $u = $request->user();
        if (!$u) return false;
        try {
            return $u->can('customer-data.approve');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function audit(?int $reqId, string $action, ?int $actor, ?string $policy, $fields = null, ?string $detail = null): void
    {
        DB::table('data_access_audit')->insert([
            'request_id'    => $reqId,
            'action'        => $action,
            'actor_id'      => $actor,
            'policy_number' => $policy,
            'fields'        => $fields ? json_encode($fields) : null,
            'detail'        => $detail,
            'created_at'    => now(),
        ]);
    }

    /** POST /data-access */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'policy_number' => 'required|string|max:80',
            'fields'        => 'required|array|min:1',
            'fields.*'      => 'string|in:' . implode(',', self::FIELDS),
            'justification' => 'required|string',
        ]);

        if (str_word_count(trim($data['justification'])) < self::MIN_WORDS) {
            return response()->json([
                'message' => 'Justification must be at least ' . self::MIN_WORDS . ' words.',
            ], 422);
        }

        $policy = DB::table('policies')->where('policyNumber', $data['policy_number'])->first(['id', 'customer_id']);
        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        $id = DB::table('data_access_requests')->insertGetId([
            'policy_number'    => $data['policy_number'],
            'fields_requested' => json_encode(array_values(array_unique($data['fields']))),
            'justification'    => $data['justification'],
            'requested_by'     => $request->user()->id ?? null,
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->audit($id, 'requested', $request->user()->id ?? null, $data['policy_number'], $data['fields'], 'request raised');

        return response()->json(['id' => $id, 'status' => 'pending', 'message' => 'Request submitted for approval.'], 201);
    }

    /** GET /data-access/mine */
    public function mine(Request $request): JsonResponse
    {
        $rows = DB::table('data_access_requests')
            ->where('requested_by', $request->user()->id ?? 0)
            ->orderByDesc('id')->limit(100)->get();
        return response()->json(['data' => $rows]);
    }

    /** GET /data-access/queue — approvers only */
    public function queue(Request $request): JsonResponse
    {
        if (!$this->canApprove($request)) {
            return response()->json(['message' => 'Not authorised to view the approvals queue.'], 403);
        }
        $rows = DB::table('data_access_requests as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.requested_by')
            ->where('d.status', 'pending')
            ->orderBy('d.id')
            ->select('d.*', DB::raw("TRIM(CONCAT(COALESCE(u.firstName,''),' ',COALESCE(u.lastName,''))) as requested_by_name"))
            ->get();
        return response()->json(['data' => $rows]);
    }

    /** POST /data-access/{id}/decide  { decision: approved|denied, note? } */
    public function decide(Request $request, int $id): JsonResponse
    {
        if (!$this->canApprove($request)) {
            return response()->json(['message' => 'Not authorised to approve data-access requests.'], 403);
        }
        $data = $request->validate([
            'decision' => 'required|in:approved,denied',
            'note'     => 'nullable|string|max:500',
        ]);

        $req = DB::table('data_access_requests')->where('id', $id)->first();
        if (!$req) return response()->json(['message' => 'Request not found.'], 404);

        // Segregation of duties (PoPIA): the person who raised a PII request
        // may NOT approve their own request, even if they hold the approver
        // permission. PII release always needs a second pair of eyes.
        if ((int) $req->requested_by === (int) ($request->user()->id ?? -1)) {
            $this->audit($id, 'self_approval_blocked', $request->user()->id ?? null, $req->policy_number,
                json_decode($req->fields_requested, true), 'requester attempted to decide own request');
            return response()->json([
                'message' => 'You cannot approve or deny your own data-access request (segregation of duties).',
            ], 403);
        }

        if ($req->status !== 'pending') {
            return response()->json(['message' => 'Request already ' . $req->status . '.'], 409);
        }

        DB::table('data_access_requests')->where('id', $id)->update([
            'status'        => $data['decision'],
            'decided_by'    => $request->user()->id ?? null,
            'decision_note' => $data['note'] ?? null,
            'decided_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->audit($id, $data['decision'], $request->user()->id ?? null, $req->policy_number,
            json_decode($req->fields_requested, true), $data['note'] ?? null);

        return response()->json(['id' => $id, 'status' => $data['decision']]);
    }

    /** GET /data-access/{id}/reveal — only for an approved request; logs the reveal */
    public function reveal(Request $request, int $id): JsonResponse
    {
        $req = DB::table('data_access_requests')->where('id', $id)->first();
        if (!$req) return response()->json(['message' => 'Request not found.'], 404);

        // Only the original requester or an approver may reveal, and only when approved.
        $uid = $request->user()->id ?? null;
        if (!($uid === (int) $req->requested_by || $this->canApprove($request))) {
            return response()->json(['message' => 'Not authorised to view this data.'], 403);
        }
        if ($req->status !== 'approved') {
            return response()->json(['message' => 'Request is ' . $req->status . ' — data not released.'], 403);
        }

        $policy = DB::table('policies')->where('policyNumber', $req->policy_number)->first(['id', 'customer_id']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        $cust = DB::table('customer')->where('id', $policy->customer_id)->first();
        $fields = json_decode($req->fields_requested, true) ?: [];
        $out = [];
        foreach ($fields as $f) {
            switch ($f) {
                case 'name':
                    $out['name'] = trim(($cust->firstName ?? '') . ' ' . ($cust->middleName ?? '') . ' ' . ($cust->lastName ?? ''));
                    break;
                case 'phone':
                    $out['phone'] = $cust->cellphone ?? null;
                    break;
                case 'id':
                    $out['id'] = $cust->omang ?? $cust->id_number ?? $cust->passport ?? null;
                    break;
                case 'address':
                    $out['address'] = $cust->physical_address ?? $cust->address ?? null;
                    break;
                case 'bank':
                    $bank = DB::table('customer_banking')
                        ->where('policy_id', $policy->id)->orderByDesc('id')->first();
                    $out['bank'] = $bank ? [
                        'bank'    => $bank->billing ?? ($bank->bank_name ?? null),
                        'account' => $bank->account_number ?? ($bank->account ?? null),
                        'branch'  => $bank->branch_code ?? null,
                    ] : null;
                    break;
            }
        }

        DB::table('data_access_requests')->where('id', $id)->update(['revealed_at' => now(), 'updated_at' => now()]);
        $this->audit($id, 'revealed', $uid, $req->policy_number, $fields, 'fields released to viewer');

        return response()->json(['data' => $out, 'policy_number' => $req->policy_number]);
    }
}
