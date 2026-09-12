<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AnomalyFindingsController — admin UI backend for the WhatsApp anomaly
 * engine's persisted findings (table: anomaly_findings).
 *
 * Distinct from ReconciliationController (which serves reconciliation_anomalies
 * for settlement/payment reconciliation). Same UX patterns — list with
 * filters, accept/dismiss/resolve actions, async CSV export — re-applied
 * to operational anomalies (duplicate policies, large claims, etc.).
 *
 * Routes (all under /api/v1, see api_v1.php):
 *   GET   anomalies/summary            → counts by type + status
 *   GET   anomalies/findings           → paginated list with filters
 *   POST  anomalies/findings/{id}/review     → set status=reviewing
 *   POST  anomalies/findings/{id}/resolve    → set status=resolved (notes required)
 *   POST  anomalies/findings/{id}/dismiss    → set status=dismissed (notes required)
 */
class AnomalyFindingsController extends Controller
{
    /**
     * GET /api/v1/anomalies/summary
     * Counts grouped by anomaly_type and status, plus open totals.
     */
    public function summary(): JsonResponse
    {
        $byType = DB::connection('mysql_system')->table('anomaly_findings')
            ->where('status', 'open')
            ->selectRaw("anomaly_type, COUNT(*) as count")
            ->groupBy('anomaly_type')
            ->pluck('count', 'anomaly_type')
            ->all();

        $byStatus = DB::connection('mysql_system')->table('anomaly_findings')
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $totalOpen = (int) ($byStatus['open'] ?? 0);

        return response()->json([
            'totalOpen' => $totalOpen,
            'byType'    => (object) $byType,
            'byStatus'  => (object) $byStatus,
        ]);
    }

    /**
     * GET /api/v1/anomalies/findings
     * List with filters + cursor-style pagination.
     */
    public function findings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anomaly_type' => 'nullable|string|max:50',
            'branch'       => 'nullable|string|max:30',
            'status'       => 'nullable|string|in:open,reviewing,resolved,dismissed',
            'search'       => 'nullable|string|max:200',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date',
            'per_page'     => 'nullable|integer|min:1|max:100',
            'page'         => 'nullable|integer|min:1',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = DB::connection('mysql_system')->table('anomaly_findings')
            ->when($validated['anomaly_type'] ?? null, fn($q, $v) => $q->where('anomaly_type', $v))
            ->when($validated['branch']       ?? null, fn($q, $v) => $q->where('branch', $v))
            ->when($validated['status']       ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($validated['date_from']    ?? null, fn($q, $v) => $q->whereDate('detected_at', '>=', $v))
            ->when($validated['date_to']      ?? null, fn($q, $v) => $q->whereDate('detected_at', '<=', $v))
            ->when($validated['search']       ?? null, function ($q, $v) {
                $like = '%' . $v . '%';
                return $q->where(function ($qq) use ($like) {
                    $qq->where('customer_name', 'like', $like)
                       ->orWhere('product_name', 'like', $like)
                       ->orWhere('device_key', 'like', $like)
                       ->orWhere('policy_numbers', 'like', $like);
                });
            })
            ->orderBy('detected_at', 'desc')
            ->orderBy('policy_count', 'desc');

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn($r) => [
            'id'             => $r->id,
            'alertKey'       => $r->alert_key,
            'anomalyType'    => $r->anomaly_type,
            'branch'         => $r->branch,
            'customerId'     => $r->customer_id,
            'customerName'   => $r->customer_name,
            'productId'      => $r->product_id,
            'productName'    => $r->product_name,
            'deviceKey'      => $r->device_key,
            'policyCount'    => $r->policy_count,
            'policyNumbers'  => $r->policy_numbers,
            'status'         => $r->status,
            'reviewedBy'     => $r->reviewed_by,
            'reviewedAt'     => $r->reviewed_at,
            'reviewNote'     => $r->review_note,
            'detectedAt'     => $r->detected_at,
        ]);

        return response()->json([
            'data'        => $items,
            'currentPage' => $paginator->currentPage(),
            'lastPage'    => $paginator->lastPage(),
            'perPage'     => $paginator->perPage(),
            'total'       => $paginator->total(),
        ]);
    }

    /**
     * POST /api/v1/anomalies/findings/{id}/review
     * Mark a finding as under review (mid-state: ops have seen it but not actioned).
     */
    public function review(int $id): JsonResponse
    {
        DB::connection('mysql_system')->table('anomaly_findings')->where('id', $id)->update([
            'status'      => 'reviewing',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);
        return response()->json(['message' => 'Finding marked as reviewing.']);
    }

    /**
     * POST /api/v1/anomalies/findings/{id}/resolve
     * Resolve a finding — operator confirms the underlying issue is fixed
     * (e.g. duplicate policy cancelled, premium adjusted).
     */
    public function resolve(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ]);
        DB::connection('mysql_system')->table('anomaly_findings')->where('id', $id)->update([
            'status'      => 'resolved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $validated['notes'],
            'updated_at'  => now(),
        ]);
        return response()->json(['message' => 'Finding resolved.']);
    }

    /**
     * POST /api/v1/anomalies/findings/{id}/dismiss
     * Dismiss a finding as not-an-issue (false positive, intentional, etc.).
     * Notes required so we can audit why the finding was waived.
     */
    public function dismiss(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ]);
        DB::connection('mysql_system')->table('anomaly_findings')->where('id', $id)->update([
            'status'      => 'dismissed',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $validated['notes'],
            'updated_at'  => now(),
        ]);
        return response()->json(['message' => 'Finding dismissed.']);
    }
}
