<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\ClaimComplaintLog;
use AlphaDirect\Exports\ComplaintsRegisterExport;
use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Consolidated admin config API — Payment Vendors, Regions, Departments,
 * Complaints, Customer Rewards/Tiers/Benefits.
 */
class AdminConfigController extends Controller
{
    // ── Payment Vendors ─────────────────────────────────────────────────
    public function paymentVendors(): JsonResponse
    {
        return response()->json(['data' => DB::table('paymentvendor')->orderBy('vendorName')->get()]);
    }

    public function storePaymentVendor(Request $request): JsonResponse
    {
        $d = $request->validate(['vendorName' => 'required|string|max:100', 'vendorLabel' => 'nullable|string|max:100', 'email' => 'nullable|email', 'telephone' => 'nullable|string|max:50', 'status' => 'nullable|integer|in:0,1']);
        $d['status'] = $d['status'] ?? 1; $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('paymentvendor')->insertGetId($d);
        return response()->json(['message' => 'Created.', 'data' => ['id' => $id]], 201);
    }

    public function updatePaymentVendor(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['vendorName' => 'required|string|max:100', 'vendorLabel' => 'nullable|string|max:100', 'email' => 'nullable|email', 'telephone' => 'nullable|string|max:50', 'status' => 'nullable|integer|in:0,1']);
        $d['updated_at'] = now();
        DB::table('paymentvendor')->where('id', $id)->update($d);
        return response()->json(['message' => 'Updated.']);
    }

    // ── Regions ─────────────────────────────────────────────────────────
    public function regions(): JsonResponse
    {
        return response()->json(['data' => DB::table('regions')->orderBy('name')->get()]);
    }

    public function storeRegion(Request $request): JsonResponse
    {
        $d = $request->validate(['name' => 'required|string|max:100']);
        $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('regions')->insertGetId($d);
        return response()->json(['message' => 'Created.', 'data' => ['id' => $id]], 201);
    }

    public function updateRegion(Request $request, int $id): JsonResponse
    {
        DB::table('regions')->where('id', $id)->update(['name' => $request->input('name'), 'updated_at' => now()]);
        return response()->json(['message' => 'Updated.']);
    }

    // ── Departments ─────────────────────────────────────────────────────
    public function departments(): JsonResponse
    {
        return response()->json(['data' => DB::table('departments')->orderBy('name')->get()]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $d = $request->validate(['name' => 'required|string|max:100']);
        $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('departments')->insertGetId($d);
        return response()->json(['message' => 'Created.', 'data' => ['id' => $id]], 201);
    }

    public function updateDepartment(Request $request, int $id): JsonResponse
    {
        DB::table('departments')->where('id', $id)->update(['name' => $request->input('name'), 'updated_at' => now()]);
        return response()->json(['message' => 'Updated.']);
    }

    // ── Complaints Register ─────────────────────────────────────────────
    // Extended into the regulatory Complaints Register: captures the fields the
    // quarterly regulator submission needs (complainant identity/contact, date
    // filed, reference, nature, handler, escalation, status, rejection reason),
    // supports supporting-document attachments, and exports a quarter's log.
    //
    // NOTE (governance): these routes are auth:sanctum only (no permission gate),
    // per the requester's decision. The register holds restricted PII (Omang /
    // passport / postal address) — a dedicated permission gate is the recommended
    // follow-up. The Omang/passport read path lives ONLY in complaintPrefill()/
    // exportComplaints() below and must never be routed through
    // ClaimFormPrefill::build() (guarded by contract test AD-POL-AI-GOV-001).

    public function complaints(Request $request): JsonResponse
    {
        $query = DB::table('claim_complaint_log as cl')
            ->leftJoin('policies as p', 'p.id', '=', 'cl.policy_id')
            ->leftJoin('claims as c', 'c.id', '=', 'cl.claim_id')
            ->leftJoin('users as u', 'u.id', '=', 'cl.added_by')
            ->leftJoin('users as h', 'h.id', '=', 'cl.handler_user_id')
            ->orderByDesc('cl.id');

        if ($request->filled('policy_id')) $query->where('cl.policy_id', $request->input('policy_id'));
        if ($request->filled('claim_id'))  $query->where('cl.claim_id', $request->input('claim_id'));
        if ($request->filled('status'))    $query->where('cl.status', $request->input('status'));

        [$from, $to] = $this->complaintDateRange($request);
        if ($from) $query->whereRaw('COALESCE(cl.date_filed, DATE(cl.created_at)) >= ?', [$from]);
        if ($to)   $query->whereRaw('COALESCE(cl.date_filed, DATE(cl.created_at)) <= ?', [$to]);

        $results = $query->select([
            'cl.*',
            'p.policyNumber',
            'c.claim_number',
            DB::raw("TRIM(CONCAT(COALESCE(u.firstName,''),' ',COALESCE(u.lastName,''))) as added_by_name"),
            DB::raw("COALESCE(NULLIF(cl.handler_name,''), TRIM(CONCAT(COALESCE(h.firstName,''),' ',COALESCE(h.lastName,'')))) as handler_name"),
        ])->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => ['current_page' => $results->currentPage(), 'per_page' => $results->perPage(), 'has_more' => $results->hasMorePages()],
        ]);
    }

    public function storeComplaint(Request $request): JsonResponse
    {
        $d = $this->validateComplaint($request);
        if (empty($d['reference_number'])) $d['reference_number'] = $this->claimNumberFor($d['claim_id'] ?? null);
        if (empty($d['handler_user_id']))  $d['handler_user_id']  = $this->claimHandlerFor($d['claim_id'] ?? null);
        $d['added_by']   = auth()->id();
        $d['updated_by'] = auth()->id();

        // Write through the audited model so status changes / edits are traceable.
        $complaint = ClaimComplaintLog::create($d);
        return response()->json(['message' => 'Complaint logged.', 'data' => ['id' => $complaint->id]], 201);
    }

    public function updateComplaint(Request $request, int $id): JsonResponse
    {
        $complaint = ClaimComplaintLog::find($id);
        if (!$complaint) return response()->json(['message' => 'Complaint not found.'], 404);

        $d = $this->validateComplaint($request);
        if (empty($d['reference_number'])) $d['reference_number'] = $this->claimNumberFor($d['claim_id'] ?? null);
        $d['updated_by'] = auth()->id();
        $complaint->update($d);

        return response()->json(['message' => 'Complaint updated.']);
    }

    /** Shared validation for create/update. Mandatory where the regulator requires it. */
    private function validateComplaint(Request $request): array
    {
        return $request->validate([
            'policy_id'                  => 'nullable|integer',
            'claim_id'                   => 'nullable|integer',
            // Complainant
            'complainant_name'           => 'required|string|max:200',
            'complainant_id_type'        => 'nullable|in:Omang,Passport',
            'complainant_omang'          => 'nullable|string|max:50|required_if:complainant_id_type,Omang',
            'complainant_passport'       => 'nullable|string|max:50|required_if:complainant_id_type,Passport',
            'complainant_phone'          => 'nullable|string|max:50',
            'complainant_email'          => 'nullable|email|max:150',
            'complainant_postal_address' => 'nullable|string|max:500',
            // Complaint
            'date_filed'                 => 'required|date_format:Y-m-d|before_or_equal:today',
            'reference_number'           => 'nullable|string|max:100',
            'complaint_of'               => 'nullable|string|max:200',
            'nature'                     => 'required|string|max:200',
            'complaint_details'          => 'required|string',
            // Handling / lifecycle
            'handler_user_id'            => 'nullable|integer',
            'handler_name'               => 'nullable|string|max:200',
            'escalation_level'           => 'nullable|string|max:150',
            'status'                     => 'required|string|max:50',
            'rejection_reason'           => 'nullable|string|max:1000',
            'resolution'                 => 'nullable|string|max:2000',
            'closed_at'                  => 'nullable|date_format:Y-m-d',
        ]);
    }

    /**
     * Prefill complainant/handler/reference from the claim's customer. This is
     * the ONLY sanctioned Omang/passport read path for complaints and is kept
     * off ClaimFormPrefill::build() (AD-POL-AI-GOV-001).
     */
    public function complaintPrefill(Request $request): JsonResponse
    {
        $claimId = (int) $request->input('claim_id');
        if (!$claimId) return response()->json(['message' => 'claim_id is required.'], 422);

        $claim = $this->resolveClaim($claimId);
        if (!$claim) return response()->json(['message' => 'Claim not found.'], 404);

        $handlerId = $claim->claim_allocated_to ? (int) $claim->claim_allocated_to : null;
        $handlerName = null;
        if ($handlerId) {
            $h = DB::table('users')->where('id', $handlerId)->first(['firstName', 'lastName']);
            if ($h) $handlerName = trim(($h->firstName ?? '') . ' ' . ($h->lastName ?? ''));
        }

        $cust = $claim->customer_id ? DB::table('customer')->where('id', $claim->customer_id)->first(['firstName', 'middleName', 'lastName', 'email', 'cellphone']) : null;
        $prof = $claim->customer_id ? DB::table('customer_profile')->where('customer_id', $claim->customer_id)->first(['omang', 'passport', 'id_type', 'address', 'second_address', 'plot_number']) : null;

        $name = $cust ? trim(implode(' ', array_filter([$cust->firstName ?? null, $cust->middleName ?? null, $cust->lastName ?? null]))) : null;
        $postal = $prof ? trim(implode(', ', array_filter([$prof->plot_number ?? null, $prof->address ?? null, $prof->second_address ?? null]))) : null;

        // Derive the ID type so the form shows only the relevant field: a
        // citizen carries an Omang, a non-citizen a passport. Fall back to the
        // stored id_type when neither value is present.
        $idType = $prof->id_type ?? null;
        if (!$idType && $prof) {
            if (!empty($prof->omang)) $idType = 'Omang';
            elseif (!empty($prof->passport)) $idType = 'Passport';
        }

        return response()->json(['data' => [
            'policy_id'                  => $claim->policy_id,
            'claim_id'                   => $claim->id,
            'reference_number'           => $claim->claim_number,
            'complainant_name'           => $name ?: null,
            'complainant_id_type'        => $idType,
            'complainant_omang'          => $prof->omang ?? null,
            'complainant_passport'       => $prof->passport ?? null,
            'complainant_phone'          => $cust->cellphone ?? null,
            'complainant_email'          => $cust->email ?? null,
            'complainant_postal_address' => $postal ?: null,
            'handler_user_id'            => $handlerId,
            'handler_name'               => $handlerName,
        ]]);
    }

    /**
     * Draft vocab for the register dropdowns. Align these to the regulator's
     * Complaints Register template (and optionally move to the lookup_data table
     * for admin configuration) before go-live.
     */
    public function complaintLookups(): JsonResponse
    {
        return response()->json(['data' => [
            'id_type'          => ['Omang', 'Passport'],
            'nature'           => ['Claims handling', 'Policy servicing', 'Premium/Billing', 'Sales/Advice', 'Product', 'Service quality', 'Repudiation/Rejection', 'Other'],
            'status'           => ['Open', 'Under Investigation', 'Escalated', 'Resolved', 'Rejected', 'Closed'],
            'escalation_level' => ['Handler', 'Team Leader', 'Manager', 'Head of Department', 'Principal Officer', 'Board/EXCO'],
        ]]);
    }

    /**
     * Quarterly regulator export. Filter by ?quarter=Q3-2026 or ?date_from&date_to,
     * and optional ?status. ?format=csv|xlsx (default xlsx). Column order mirrors
     * the regulator's register (align headings to the template before go-live).
     */
    public function exportComplaints(Request $request)
    {
        [$from, $to] = $this->complaintDateRange($request);

        $query = DB::table('claim_complaint_log as cl')
            ->leftJoin('claims as c', 'c.id', '=', 'cl.claim_id')
            ->leftJoin('users as h', 'h.id', '=', 'cl.handler_user_id')
            ->orderBy(DB::raw('COALESCE(cl.date_filed, DATE(cl.created_at))'));

        if ($request->filled('status')) $query->where('cl.status', $request->input('status'));
        if ($from) $query->whereRaw('COALESCE(cl.date_filed, DATE(cl.created_at)) >= ?', [$from]);
        if ($to)   $query->whereRaw('COALESCE(cl.date_filed, DATE(cl.created_at)) <= ?', [$to]);

        $rows = $query->select([
            'cl.*',
            'c.claim_number',
            DB::raw("COALESCE(NULLIF(cl.handler_name,''), TRIM(CONCAT(COALESCE(h.firstName,''),' ',COALESCE(h.lastName,'')))) as handler_name"),
        ])->get();

        $headings = [
            'Name of Complainant', 'Omang (Citizen)', 'Passport (Non-Citizen)',
            'Contact Telephone', 'Contact Email', 'Postal Address',
            'Date Complaint Filed', 'Reference/Contract Number', 'Nature/Description of Complaint',
            'Name of Employee Handling', 'Highest Escalation Level', 'Current Status', 'Reason for Rejection',
        ];

        $data = $rows->map(function ($r) {
            $desc = trim(($r->nature ? $r->nature . ': ' : '') . (string) ($r->complaint_details ?? ''));
            return [
                (string) ($r->complainant_name ?? ''),
                (string) ($r->complainant_omang ?? ''),
                (string) ($r->complainant_passport ?? ''),
                (string) ($r->complainant_phone ?? ''),
                (string) ($r->complainant_email ?? ''),
                (string) ($r->complainant_postal_address ?? ''),
                (string) ($r->date_filed ?? ''),
                (string) ($r->reference_number ?: ($r->claim_number ?? '')),
                $desc,
                (string) ($r->handler_name ?? ''),
                (string) ($r->escalation_level ?? ''),
                (string) ($r->status ?? ''),
                (string) ($r->rejection_reason ?? ''),
            ];
        })->toArray();

        $label = $request->filled('quarter')
            ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('quarter'))
            : (($from ?: 'all') . '_to_' . ($to ?: 'all'));
        $isCsv = strtolower((string) $request->input('format', 'xlsx')) === 'csv';
        $writer = $isCsv ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return (new ComplaintsRegisterExport($headings, $data))
            ->download('complaints-register-' . $label . '.' . ($isCsv ? 'csv' : 'xlsx'), $writer);
    }

    // ── Complaint supporting documents (reuse claim_attachments) ─────────
    public function complaintDocuments(int $id): JsonResponse
    {
        $rows = DB::table('claim_attachments')->where('complaint_id', $id)->orderByDesc('id')->get();
        $docs = $rows->map(function ($r) {
            $paths = $this->attachmentPaths($r);
            return [
                'id'         => $r->id,
                'name'       => $r->name ?? null,
                'files'      => array_map(fn ($p) => ['path' => $p, 'url' => $this->cdnUrl($p), 'filename' => basename($p)], $paths),
                'created_at' => $r->created_at ?? null,
            ];
        });
        return response()->json(['data' => $docs]);
    }

    public function uploadComplaintDocument(Request $request, int $id): JsonResponse
    {
        $complaint = DB::table('claim_complaint_log')->where('id', $id)->first();
        if (!$complaint) return response()->json(['message' => 'Complaint not found.'], 404);

        $request->validate([
            'files'   => 'nullable|array|max:10',
            'files.*' => 'file|max:40960',
            'file'    => 'nullable|file|max:40960',
            'name'    => 'nullable|string|max:255',
        ]);

        $files = $request->file('files') ?: ($request->file('file') ? [$request->file('file')] : []);
        if (empty($files)) return response()->json(['message' => 'No file provided.'], 422);

        $dir = 'MIS/' . ($complaint->claim_id ?? '0') . '/Complaints/' . $id;
        $paths = [];
        foreach ($files as $f) $paths[] = $this->storeWithOriginalName($f, $dir);

        $docId = DB::table('claim_attachments')->insertGetId([
            'claim_id'     => $complaint->claim_id,
            'complaint_id' => $id,
            'name'         => $request->input('name') ?: 'Complaint correspondence',
            'attachment'   => serialize($paths),
            'file_name'    => $paths[0] ?? null,
            'file_type'    => $files[0]->getClientMimeType(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json(['message' => 'Uploaded.', 'data' => ['id' => $docId]], 201);
    }

    public function deleteComplaintDocument(int $id, int $docId): JsonResponse
    {
        $row = DB::table('claim_attachments')->where('id', $docId)->where('complaint_id', $id)->first();
        if (!$row) return response()->json(['message' => 'Document not found.'], 404);

        foreach ($this->attachmentPaths($row) as $p) {
            try { Storage::disk('s3')->delete($p); } catch (\Throwable $e) { /* best-effort */ }
        }
        DB::table('claim_attachments')->where('id', $docId)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ── Complaint helpers ────────────────────────────────────────────────
    private function complaintDateRange(Request $request): array
    {
        if ($request->filled('quarter') && preg_match('/^Q([1-4])-(\d{4})$/', trim((string) $request->input('quarter')), $m)) {
            $q = (int) $m[1];
            $y = (int) $m[2];
            $startMonth = ($q - 1) * 3 + 1;
            $from = sprintf('%04d-%02d-01', $y, $startMonth);
            $to = date('Y-m-t', mktime(0, 0, 0, $startMonth + 2, 1, $y));
            return [$from, $to];
        }
        return [
            $request->filled('date_from') ? (string) $request->input('date_from') : null,
            $request->filled('date_to') ? (string) $request->input('date_to') : null,
        ];
    }

    private function resolveClaim(int $claimId): ?object
    {
        $c = DB::table('claims')->where('id', $claimId)->first();
        if (!$c) $c = DB::table('new_claims')->where('id', $claimId)->first();
        if (!$c) return null;

        $customerId = $c->customer_id ?? null;
        if (!$customerId && !empty($c->policy_id)) {
            $customerId = DB::table('policies')->where('id', $c->policy_id)->value('customer_id');
        }

        return (object) [
            'id'                 => $c->id,
            'policy_id'          => $c->policy_id ?? null,
            'customer_id'        => $customerId,
            'claim_number'       => $c->claim_number ?? null,
            'claim_allocated_to' => $c->claim_allocated_to ?? null,
        ];
    }

    private function claimNumberFor(?int $claimId): ?string
    {
        if (!$claimId) return null;
        $claim = $this->resolveClaim($claimId);
        return $claim->claim_number ?? null;
    }

    private function claimHandlerFor(?int $claimId): ?int
    {
        if (!$claimId) return null;
        $claim = $this->resolveClaim($claimId);
        return !empty($claim->claim_allocated_to) ? (int) $claim->claim_allocated_to : null;
    }

    private function attachmentPaths(object $row): array
    {
        $paths = [];
        if (!empty($row->attachment)) {
            $u = @unserialize($row->attachment);
            if (is_array($u)) $paths = $u;
        }
        if (empty($paths) && !empty($row->file_name)) $paths = [$row->file_name];
        return array_values(array_filter($paths));
    }

    /** Build a CDN URL from an S3 path (mirrors ClaimsV2Controller::cdnUrl). */
    private function cdnUrl(?string $path): ?string
    {
        if (empty($path)) return null;
        $cdn = config('filesystems.disks.s3.cdn_url') ?: env('AWS_CLOUDFRONT');
        if (!$cdn) return null;
        $path = str_replace(['\/', ' '], ['/', '%20'], $path);
        return rtrim($cdn, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Store a file preserving its original name, using the codebase's dominant
     * working upload call — Storage::disk('s3')->put(path, contents, 'public')
     * — rather than UploadedFile::storeAs(). The 'public' visibility matches how
     * the app serves these objects via CloudFront (storeAs uploads private, so
     * the cdnUrl would 403). Each file lands in its own UUID sub-folder so the
     * original filename is preserved without collisions.
     */
    private function storeWithOriginalName(\Illuminate\Http\UploadedFile $file, string $dir): string
    {
        $safe = preg_replace('#[/\\\\]+#', '_', $file->getClientOriginalName());
        $safe = trim(preg_replace('/[#?%&]+/', '_', (string) $safe));
        if ($safe === '') $safe = 'file.' . ($file->getClientOriginalExtension() ?: 'dat');
        $path = rtrim($dir, '/') . '/' . (string) Str::uuid() . '/' . $safe;
        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), 'public');
        return $path;
    }

    // ── Reward Tiers ────────────────────────────────────────────────────
    public function rewardTiers(): JsonResponse
    {
        $tiers = DB::table('tiers')->orderBy('level_point')->get();
        return response()->json(['data' => $tiers]);
    }

    public function storeRewardTier(Request $request): JsonResponse
    {
        $d = $request->validate([
            'name' => 'required|string|max:100', 'label' => 'nullable|string|max:100',
            'description' => 'nullable|string', 'level_point' => 'required|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
        ]);
        $d['status'] = $d['status'] ?? 1; $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('tiers')->insertGetId($d);
        return response()->json(['message' => 'Tier created.', 'data' => ['id' => $id]], 201);
    }

    public function updateRewardTier(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'name' => 'required|string|max:100', 'label' => 'nullable|string|max:100',
            'description' => 'nullable|string', 'level_point' => 'required|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
        ]);
        $d['updated_at'] = now();
        DB::table('tiers')->where('id', $id)->update($d);
        return response()->json(['message' => 'Tier updated.']);
    }

    // ── Benefits ────────────────────────────────────────────────────────
    public function benefits(): JsonResponse
    {
        return response()->json(['data' => DB::table('benefits')->orderBy('tag')->get()]);
    }

    public function storeBenefit(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tag' => 'required|string|max:100', 'type' => 'nullable|string|max:50',
            'price' => 'nullable|numeric', 'point' => 'nullable|integer', 'status' => 'nullable|integer|in:0,1',
        ]);
        $d['status'] = $d['status'] ?? 1; $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('benefits')->insertGetId($d);
        return response()->json(['message' => 'Benefit created.', 'data' => ['id' => $id]], 201);
    }

    // ── Customer Rewards ────────────────────────────────────────────────
    public function customerRewards(Request $request): JsonResponse
    {
        $query = DB::table('customer_rewards as cr')
            ->leftJoin('customer as c', 'c.id', '=', 'cr.customer_id')
            ->leftJoin('benefits as b', 'b.id', '=', 'cr.benefit_id')
            ->orderByDesc('cr.id');

        if ($request->has('customer_id')) $query->where('cr.customer_id', $request->input('customer_id'));

        $results = $query->select([
            'cr.*', DB::raw("CONCAT(c.firstName,' ',c.lastName) as customer_name"), 'b.tag as benefit_name',
        ])->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => ['current_page' => $results->currentPage(), 'per_page' => $results->perPage(), 'has_more' => $results->hasMorePages()],
        ]);
    }
}
