<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Policy;
use AlphaDirect\Services\Bonds\BondsIssuanceGate;
use AlphaDirect\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Bonds & Guarantees (product 23): collateral proof documents.
 *
 * Split of duties UW asked for, 2026-08-29:
 *   - upload    — ANY authenticated user who can reach the policy;
 *   - approve   — EXCO only, i.e. the `bonds-approve` permission that already
 *                 gates bond approval and collateral confirmation;
 *   - issue     — blocked until an approved document exists for the action
 *                 (Services\Bonds\BondsIssuanceGate::collateralDocumentBlocker).
 *
 * Documents are stored per policy AND per action: each transaction carries
 * its own proof, because each one moves the exposure under the bond.
 *
 * Storage mirrors uploadWording() in SpecialistCoverageController — the
 * shared StorageService (S3 primary, local fallback), never a bare
 * Storage::put, so a mis-configured bucket cannot 500 the upload.
 */
class BondsCollateralDocumentController extends Controller
{
    private const TABLE = 'bonds_collateral_documents';

    /** Max upload size in KB (20 MB), same ceiling as the policy wording PDF. */
    private const MAX_KB = 20480;

    /**
     * GET /policies/{policyId}/bonds/collateral-documents
     *
     * Optional ?action_id=N narrows to one transaction; without it the whole
     * policy's document history is returned, newest first, so the schedule
     * form can show what was approved on earlier transactions too.
     */
    public function index(Request $request, int $policyId): JsonResponse
    {
        Policy::findOrFail($policyId);
        if (!Schema::hasTable(self::TABLE)) {
            return response()->json([
                'data'        => [],
                'may_approve' => BondsIssuanceGate::userMayApprove(),
                'warning'     => 'Collateral document storage is not migrated on this environment. Ask an admin to run migrations.',
            ]);
        }

        $rows = DB::table(self::TABLE)
            ->where('policy_id', $policyId)
            ->when($request->filled('action_id'), fn ($q) => $q->where('action_id', (int) $request->input('action_id')))
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => $this->present($r))
            ->values();

        return response()->json([
            'data'        => $rows,
            // Drives the FE: EXCO sees Approve/Reject, everyone else sees the
            // "awaiting EXCO approval" note.
            'may_approve' => BondsIssuanceGate::userMayApprove(),
        ]);
    }

    /**
     * POST /policies/{policyId}/bonds/collateral-documents
     *
     * Upload one collateral document. Open to any user who can reach the
     * policy — capture is not the control, approval is.
     */
    public function store(Request $request, int $policyId): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        if (!Schema::hasTable(self::TABLE)) {
            return response()->json(['error' => 'Collateral document storage is missing. Ask an admin to run: php artisan migrate'], 500);
        }

        $request->validate([
            'collateral_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . self::MAX_KB,
            'record_id'       => 'nullable|integer',
            'action_id'       => 'nullable|integer',
            'document_type'   => 'nullable|string|max:255',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $file = $request->file('collateral_file');

        // Scope: the bond schedule row the operator uploaded from (when there
        // is one) decides the action; otherwise the caller's action_id; else
        // the policy's latest action. Same precedence as buildPayload().
        $bondRow  = null;
        $recordId = (int) $request->input('record_id', 0);
        if ($recordId > 0 && Schema::hasTable('bonds_coverages')) {
            $bondRow = DB::table('bonds_coverages')->where('id', $recordId)->where('policy_id', $policyId)->first();
        }
        $actionId = (int) ($request->input('action_id')
            ?: ($bondRow->action_id ?? 0)
            ?: (int) DB::table('policy_actions')->where('policy_id', $policyId)->orderByDesc('id')->value('id'));

        try {
            $stored = app(StorageService::class)
                ->putFileWithFallback("bonds_collateral/{$policy->policyNumber}", $file);
        } catch (\Throwable $e) {
            Log::error('BondsCollateralDocument upload storage failed: ' . $e->getMessage(), [
                'policy_id' => $policyId, 'action_id' => $actionId,
            ]);
            return response()->json([
                'error' => 'Upload storage is temporarily unavailable. The document was NOT saved. Please try again or contact support.',
            ], 503);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'policy_id'          => $policyId,
            'action_id'          => $actionId ?: null,
            'bonds_coverage_id'  => $bondRow->id ?? null,
            'policy_coverage_id' => $bondRow->policy_coverage_id ?? null,
            'document_type'      => $request->input('document_type') ?: ($bondRow->collateral_type ?? null),
            'notes'              => $request->input('notes'),
            'file_path'          => $stored['path'],
            'file_name'          => $file->getClientOriginalName(),
            'mime_type'          => $file->getClientMimeType(),
            'file_size'          => $file->getSize(),
            'disk'               => $stored['disk'] ?? null,
            'status'             => 'PENDING',
            'uploaded_by'        => auth()->id(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())
            ->withProperties(['document_id' => $id, 'action_id' => $actionId, 'file' => $file->getClientOriginalName()])
            ->log('Bond Collateral Document Uploaded (pending EXCO approval)');

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return response()->json([
            'message' => 'Collateral document uploaded. It must be approved by EXCO before this policy can be issued.',
            'data'    => $this->present($row),
        ], 201);
    }

    /**
     * POST /policies/{policyId}/bonds/collateral-documents/{docId}/approve
     *
     * EXCO decision. `reject=1` (with an optional `reason`) records a
     * rejection instead; either way the stamp is auditable and a rejected
     * document never satisfies the issue gate.
     */
    public function approve(Request $request, int $policyId, int $docId): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        if (!Schema::hasTable(self::TABLE)) {
            return response()->json(['error' => 'Collateral document storage is missing. Ask an admin to run: php artisan migrate'], 500);
        }

        // The one control. No Super Admin bypass — same posture as
        // BondsIssuanceGate::userMayApprove(), which is fail-closed until
        // `bonds-approve` is created in /roles.
        if (!BondsIssuanceGate::userMayApprove()) {
            return response()->json([
                'error' => 'Collateral documents may only be approved by EXCO. You do not hold the bond approval right.',
            ], 403);
        }

        $row = DB::table(self::TABLE)->where('id', $docId)->where('policy_id', $policyId)->whereNull('deleted_at')->first();
        if (!$row) return response()->json(['error' => 'Collateral document not found.'], 404);

        $reject = filter_var($request->input('reject', false), FILTER_VALIDATE_BOOLEAN);
        $reason = trim((string) $request->input('reason', ''));

        DB::table(self::TABLE)->where('id', $docId)->update([
            'status'           => $reject ? 'REJECTED' : 'APPROVED',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'rejection_reason' => $reject ? ($reason ?: null) : null,
            'updated_at'       => now(),
        ]);

        activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())
            ->withProperties(['document_id' => $docId, 'action_id' => $row->action_id, 'reason' => $reason ?: null])
            ->log($reject ? 'Bond Collateral Document Rejected' : 'Bond Collateral Document Approved');

        return response()->json([
            'message' => $reject ? 'Collateral document rejected.' : 'Collateral document approved.',
            'data'    => $this->present(DB::table(self::TABLE)->where('id', $docId)->first()),
        ]);
    }

    /**
     * DELETE /policies/{policyId}/bonds/collateral-documents/{docId}
     *
     * Remove a document. The uploader may withdraw their own upload while it
     * is still PENDING or REJECTED; taking down an APPROVED document removes
     * proof the issue gate relies on, so that is EXCO-only.
     */
    public function destroy(int $policyId, int $docId): JsonResponse
    {
        $policy = Policy::findOrFail($policyId);
        if (!Schema::hasTable(self::TABLE)) {
            return response()->json(['error' => 'Collateral document storage is missing. Ask an admin to run: php artisan migrate'], 500);
        }

        $row = DB::table(self::TABLE)->where('id', $docId)->where('policy_id', $policyId)->whereNull('deleted_at')->first();
        if (!$row) return response()->json(['error' => 'Collateral document not found.'], 404);

        $mayApprove = BondsIssuanceGate::userMayApprove();
        $isUploader = (int) ($row->uploaded_by ?? 0) === (int) auth()->id();

        if (strtoupper((string) $row->status) === 'APPROVED' && !$mayApprove) {
            return response()->json(['error' => 'An approved collateral document may only be removed by EXCO.'], 403);
        }
        if (!$isUploader && !$mayApprove) {
            return response()->json(['error' => 'You may only remove a collateral document you uploaded.'], 403);
        }

        DB::table(self::TABLE)->where('id', $docId)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        activity('Specialist Coverage')->performedOn($policy)->causedBy(auth()->user())
            ->withProperties(['document_id' => $docId, 'status' => $row->status])
            ->log('Bond Collateral Document Removed');

        return response()->json(['message' => 'Collateral document removed.']);
    }

    /**
     * GET /policies/{policyId}/bonds/collateral-documents/{docId}/download
     *
     * Streams the file inline. The stored disk is tried first and the others
     * after it, because a file can be migrated between disks.
     */
    public function download(int $policyId, int $docId)
    {
        Policy::findOrFail($policyId);
        if (!Schema::hasTable(self::TABLE)) {
            return response()->json(['error' => 'Collateral document storage is missing.'], 500);
        }

        $row = DB::table(self::TABLE)->where('id', $docId)->where('policy_id', $policyId)->whereNull('deleted_at')->first();
        if (!$row || empty($row->file_path)) {
            return response()->json(['error' => 'Collateral document not found.'], 404);
        }

        $disks = array_values(array_unique(array_filter([$row->disk, 'public', 's3', 'documents', 'local'])));
        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($row->file_path)) {
                    return response($disk->get($row->file_path), 200, [
                        'Content-Type'        => $row->mime_type ?: 'application/octet-stream',
                        'Content-Disposition' => 'inline; filename="' . addslashes($row->file_name ?: 'collateral') . '"',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("BondsCollateralDocument: disk {$diskName} check failed: {$e->getMessage()}");
            }
        }

        return response()->json(['error' => 'File not found in storage.'], 404);
    }

    /** Shape one row for the FE, with the uploader/approver names resolved. */
    private function present($row): array
    {
        $names = $this->userNames([$row->uploaded_by ?? null, $row->approved_by ?? null]);

        return [
            'id'                => (int) $row->id,
            'policy_id'         => (int) $row->policy_id,
            'action_id'         => $row->action_id ? (int) $row->action_id : null,
            'bonds_coverage_id' => $row->bonds_coverage_id ? (int) $row->bonds_coverage_id : null,
            'document_type'     => $row->document_type,
            'notes'             => $row->notes,
            'file_name'         => $row->file_name,
            'file_size'         => $row->file_size ? (int) $row->file_size : null,
            'status'            => strtoupper((string) $row->status),
            'uploaded_by'       => $row->uploaded_by ? (int) $row->uploaded_by : null,
            'uploaded_by_name'  => $names[(int) ($row->uploaded_by ?? 0)] ?? null,
            'uploaded_at'       => $row->created_at,
            'approved_by'       => $row->approved_by ? (int) $row->approved_by : null,
            'approved_by_name'  => $names[(int) ($row->approved_by ?? 0)] ?? null,
            'approved_at'       => $row->approved_at,
            'rejection_reason'  => $row->rejection_reason,
        ];
    }

    /**
     * @param  array<int,int|null>  $ids
     * @return array<int,string>
     */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) return [];

        try {
            return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
