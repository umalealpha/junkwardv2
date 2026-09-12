<?php

namespace AlphaDirect\Http\Controllers\Api\V1\Refunds;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Models\RefundRequestDocument;
use AlphaDirect\Services\Refunds\RefundRequestService;
use AlphaDirect\Services\Refunds\RefundWorkflowException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Customer Refund Engine — creator + shared read surface.
 *
 * Routes (prefixed /api/v1, gating in api_v1.php):
 *   GET    refund-requests                    index    (area-scoped list)
 *   GET    refund-requests/metrics            metrics  (dashboard cards)
 *   GET    refund-requests/export             export   (filterable CSV)
 *   GET    refund-requests/{id}               show
 *   GET    refund-requests/{id}/documents/{docId}/download
 *   POST   refund-requests                    store    (draft)
 *   PUT    refund-requests/{id}               update   (draft/rejected only)
 *   POST   refund-requests/{id}/documents     uploadDocument
 *   POST   refund-requests/{id}/submit        submit
 *
 * Route middleware enforces the ACTION permission; every handler here ALSO
 * scopes by the caller's AREA permissions (refund_area_mis / refund_area_dc)
 * because Spatie permissions don't row-scope — that in-query filter is the
 * DPA "no cross-area access" control.
 */
class RefundRequestController extends Controller
{
    public function __construct(private RefundRequestService $service) {}

    // ─── Reads ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $areas = RefundRequestService::allowedAreas($request->user());
        if (!$areas) {
            return response()->json(['error' => 'You are not assigned to a refund area (MIS or Domestic & Commercial).'], 403);
        }

        $q = RefundRequest::query()->whereIn('area', $areas)->withCount('documents');

        if (($v = $request->input('area')) && in_array($v, $areas, true)) $q->where('area', $v);
        if ($v = $request->input('status'))        $q->where('status', $v);
        if ($v = $request->input('policy_number')) $q->where('policy_number', 'like', "%{$v}%");
        if ($v = $request->input('graphite_ref'))  $q->where('graphite_ref', 'like', "%{$v}%");
        if ($v = $request->input('date_from'))     $q->whereDate('created_at', '>=', $v);
        if ($v = $request->input('date_to'))       $q->whereDate('created_at', '<=', $v);
        if ($request->boolean('mine'))             $q->where('created_by', $request->user()->id);
        // Finance-requested filters (Keetile 2026-07-30): client name + amount band.
        if ($v = $request->input('customer_name')) $q->where('customer_name', 'like', "%{$v}%");
        if (($v = $request->input('amount_min')) !== null && $v !== '') $q->where('refund_amount', '>=', (float) $v);
        if (($v = $request->input('amount_max')) !== null && $v !== '') $q->where('refund_amount', '<=', (float) $v);
        if ($request->boolean('flagged'))          $q->where('fraud_score', '>', 0);

        // "Deleted" view — CFO / Super Admin only. Lets the Deleted tab list
        // soft-deleted requests (so a deletion never means the entry silently
        // disappears) and restore them, instead of needing IT + the database.
        $trashed = $request->input('trashed');
        if (in_array($trashed, ['only', 'with'], true) && $request->user()->can('refund-cfo-approve')) {
            $trashed === 'only' ? $q->onlyTrashed() : $q->withTrashed();
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 25)));
        $page    = $q->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $r = RefundRequest::with(['documents', 'events' => fn ($q) => $q->orderBy('created_at')])
            ->findOrFail($id);
        try {
            RefundRequestService::assertAreaAccess($request->user(), $r);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
        return response()->json(['data' => $r]);
    }

    /** Dashboard metrics — SOP: daily value + counts per state, area-scoped. */
    public function metrics(Request $request): JsonResponse
    {
        $areas = RefundRequestService::allowedAreas($request->user());
        if (!$areas) {
            return response()->json(['error' => 'You are not assigned to a refund area.'], 403);
        }

        $base = fn () => RefundRequest::query()->whereIn('area', $areas);

        $statusCounts = $base()->select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(refund_amount) v'))
            ->groupBy('status')->get()
            ->mapWithKeys(fn ($row) => [$row->status => ['count' => (int) $row->c, 'value' => (float) $row->v]]);

        $today = now()->toDateString();
        $todayRow = $base()->whereDate('created_at', $today)
            ->select(DB::raw('COUNT(*) c'), DB::raw('COALESCE(SUM(refund_amount),0) v'))->first();

        // 14-day intake series for the dashboard chart.
        $series = $base()->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(created_at) d'), DB::raw('COUNT(*) c'), DB::raw('SUM(refund_amount) v'))
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('d')->get();

        return response()->json(['data' => [
            'areas'         => $areas,
            'status_counts' => $statusCounts,
            'today'         => ['count' => (int) ($todayRow->c ?? 0), 'value' => (float) ($todayRow->v ?? 0)],
            'series'        => $series,
        ]]);
    }

    /** Filterable CSV export (same filters as index; capped at 10k rows). */
    public function export(Request $request)
    {
        $areas = RefundRequestService::allowedAreas($request->user());
        if (!$areas) {
            return response()->json(['error' => 'You are not assigned to a refund area.'], 403);
        }

        $q = RefundRequest::query()->whereIn('area', $areas);
        if (($v = $request->input('area')) && in_array($v, $areas, true)) $q->where('area', $v);
        if ($v = $request->input('status'))        $q->where('status', $v);
        if ($v = $request->input('policy_number')) $q->where('policy_number', 'like', "%{$v}%");
        if ($v = $request->input('date_from'))     $q->whereDate('created_at', '>=', $v);
        if ($v = $request->input('date_to'))       $q->whereDate('created_at', '<=', $v);
        if ($v = $request->input('customer_name')) $q->where('customer_name', 'like', "%{$v}%");
        if (($v = $request->input('amount_min')) !== null && $v !== '') $q->where('refund_amount', '>=', (float) $v);
        if (($v = $request->input('amount_max')) !== null && $v !== '') $q->where('refund_amount', '<=', (float) $v);

        $cap  = 10000;
        $rows = $q->orderByDesc('created_at')->limit($cap + 1)->get();
        $truncated = $rows->count() > $cap;
        if ($truncated) {
            $rows = $rows->take($cap);
        }

        // A bulk PII extract must be at least as auditable as revealing one
        // account number. Records who exported what filters, and whether the
        // pull was silently truncated (an incomplete audit pull that LOOKS
        // complete is its own hazard).
        $this->service->auditPiiAccess(null, $request->user(), 'export_csv', [
            'rows'      => $rows->count(),
            'truncated' => $truncated,
            'filters'   => $request->only(['area', 'status', 'policy_number', 'date_from', 'date_to']),
        ]);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="refund-requests-' . now()->format('Ymd-His') . '.csv"',
        ];
        // Audit-complete: reason_code decides whether a credit note was owed,
        // and the approver identities / fraud score are what an auditor tests the
        // four-eyes and screening controls against. referenceNumber-side joins
        // use graphite_ref + omni_paid_ref.
        $cols = ['graphite_ref', 'area', 'status', 'policy_number', 'product_name', 'customer_name',
                 'agent_name', 'refund_amount', 'currency', 'collection_method', 'reason_code', 'reason',
                 'bank_name', 'branch_name', 'branch_code', 'account_last4',
                 'fraud_score', 'ai_greenlight', 'vehicle_not_client', 'bank_account_confirmed',
                 'created_by', 'reviewed_by', 'review_comment', 'approved_by', 'approved_reason',
                 'second_approved_by', 'cfo_approved_by', 'rejected_by', 'rejected_reason',
                 'omni_status', 'omni_paid_ref', 'handed_off_at',
                 'manual_paid_at', 'manual_paid_ref',
                 'submitted_at', 'reviewed_at', 'approved_at', 'second_approved_at',
                 'cfo_approved_at', 'omni_paid_at', 'created_at'];

        return response()->stream(function () use ($rows, $cols, $truncated, $cap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $cols);
            foreach ($rows as $r) {
                fputcsv($out, array_map(function ($c) use ($r) {
                    $v = $r->{$c} ?? '';
                    if (is_bool($v)) return $v ? 'Yes' : 'No';
                    return (string) $v;
                }, $cols));
            }
            // Never let a capped pull masquerade as a complete one.
            if ($truncated) {
                fputcsv($out, ['TRUNCATED — only the most recent ' . $cap
                    . ' rows are included. Narrow the date range for a complete extract.']);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function downloadDocument(Request $request, int $id, int $docId)
    {
        $r = RefundRequest::findOrFail($id);
        try {
            RefundRequestService::assertAreaAccess($request->user(), $r);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
        $doc = RefundRequestDocument::where('refund_request_id', $r->id)->findOrFail($docId);

        // Bank statements carry the full account number and the customer's
        // transaction history — the same PII the reveal endpoint audits. Log the
        // download too, or the audited reveal is trivially sidestepped.
        $this->service->auditPiiAccess($r, $request->user(), 'download_document', [
            'doc_id' => $doc->id, 'doc_type' => $doc->doc_type,
        ]);

        // Streamed through the API with the caller's Bearer token — the S3
        // object itself is never publicly reachable.
        return Storage::disk('s3')->download($doc->file_path, $doc->original_name ?: basename($doc->file_path));
    }

    /**
     * Reveal the full bank account number so a reviewer/approver can verify it
     * against the uploaded bank statement (CFO 2026-07-28 — the verifier needs
     * the real number). Area-scoped; the reveal is logged to the audit trail.
     */
    public function revealAccount(Request $request, int $id): JsonResponse
    {
        $r = RefundRequest::findOrFail($id);
        try {
            RefundRequestService::assertAreaAccess($request->user(), $r);
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
        $number = $this->service->revealAccountNumber($r, $request->user());
        return response()->json(['data' => [
            'account_number' => $number,
            'account_last4'  => $r->account_last4,
        ]]);
    }

    // ─── Writes (Creator) ────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, creating: true);
        return $this->guarded(function () use ($data, $request) {
            $this->assertPayloadArea($request, $data['area'] ?? '');
            $r = $this->service->createDraft($data, $request->user());
            return response()->json(['data' => $r->fresh(['documents'])], 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $r = RefundRequest::findOrFail($id);
        $data = $this->validatePayload($request, creating: false);
        return $this->guarded(function () use ($r, $data, $request) {
            RefundRequestService::assertAreaAccess($request->user(), $r);
            $r2 = $this->service->updateDraft($r, $data, $request->user());
            return response()->json(['data' => $r2->fresh(['documents'])]);
        });
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $r = RefundRequest::findOrFail($id);
        $request->validate([
            'file'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'doc_type' => 'required|string|in:' . implode(',', RefundRequestDocument::TYPES),
        ]);
        return $this->guarded(function () use ($r, $request) {
            RefundRequestService::assertAreaAccess($request->user(), $r);
            $doc = $this->service->addDocument($r, $request->file('file'),
                (string) $request->input('doc_type'), $request->user());
            return response()->json(['data' => $doc], 201);
        });
    }

    /**
     * POST refund-requests/{id}/reset-to-draft — return a rejected refund to the
     * intaker for correction (Finance ask, Keetile 2026-08-18).
     */
    public function resetToDraft(Request $request, int $id): JsonResponse
    {
        $r = RefundRequest::findOrFail($id);
        return $this->guarded(function () use ($r, $request) {
            RefundRequestService::assertAreaAccess($request->user(), $r);
            $r2 = $this->service->resetToDraft($r, $request->user());
            return response()->json(['data' => $r2->fresh(['documents'])]);
        });
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $r = RefundRequest::findOrFail($id);
        return $this->guarded(function () use ($r, $request) {
            RefundRequestService::assertAreaAccess($request->user(), $r);
            $r2 = $this->service->submit($r, $request->user());
            return response()->json(['data' => $r2]);
        });
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function validatePayload(Request $request, bool $creating): array
    {
        $rules = [
            'policy_number'      => ($creating ? 'required' : 'sometimes') . '|string|max:80',
            'refund_amount'      => ($creating ? 'required' : 'sometimes') . '|numeric|gt:0',
            'area'               => ($creating ? 'required' : 'prohibited') . '|string|in:' . implode(',', RefundRequest::AREAS),
            'currency'           => 'sometimes|string|in:BWP',
            'product_name'       => 'nullable|string|max:191',
            'customer_name'      => 'nullable|string|max:191',
            'agent_name'         => 'nullable|string|max:191',
            'reason'             => 'nullable|string|max:500',
            // Taxonomy drives the accounting treatment: return-premium codes
            // queue a Credit Note for Finance when paid; plain codes are cash-only.
            'reason_code'        => 'nullable|string|in:' . implode(',', array_keys(
                RefundRequest::RETURN_PREMIUM_REASON_CODES + RefundRequest::PLAIN_REASON_CODES)),
            'collection_method'  => 'nullable|string|max:12',
            'bank_name'          => 'nullable|string|max:120',
            'branch_name'        => 'nullable|string|max:120',
            'branch_code'        => 'nullable|string|max:20',
            'account_number'     => 'nullable|string|max:40',
            'vehicle_not_client' => 'sometimes|boolean',
            'ai_greenlight'      => 'sometimes|boolean',
            'ai_evidence'        => 'sometimes|array',
        ];
        return $request->validate($rules);
    }

    /** A creator may only open requests in an area they hold. */
    private function assertPayloadArea(Request $request, string $area): void
    {
        if (!in_array($area, RefundRequestService::allowedAreas($request->user()), true)) {
            throw new RefundWorkflowException(
                'You are not assigned to this refund area (MIS and Domestic & Commercial are separate).', 403);
        }
    }

    private function guarded(\Closure $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RefundWorkflowException $e) {
            return response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
    }
}
