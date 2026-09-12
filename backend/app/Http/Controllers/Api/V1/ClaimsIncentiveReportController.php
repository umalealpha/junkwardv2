<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\ClaimsIncentiveReport;
use AlphaDirect\Services\IntegrationSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ClaimsIncentiveReportController — Phase-3 Claims-Tracker migration.
 *
 * Reports the % of MOTOR claims routed to APPROVED panel-beaters and GLASS
 * claims routed to APPROVED glass suppliers, plus a small write endpoint to
 * mark a supplier as an approved panel-beater / glass supplier.
 *
 * Read-only over existing claims / claim_quotes / suppliers data — it never
 * changes claim, status or financial flows. Aggregate only (no customer PII).
 *
 * Feature flag: `claims_incentive_report` (Admin -> Integrations, default OFF).
 * When the flag is OFF every endpoint returns 403 so the surface is inert until
 * armed on UAT. RBAC (route-level): Claims Manager | Admin | Super Admin.
 *
 * Claim -> supplier signal (assumption, documented for Claims/UW sign-off):
 * the supplier that "handled" a claim is the supplier on the claim's ACCEPTED
 * quote (claim_quotes.status = 'Accepted'; set by ClaimsV2Controller::acceptQuote,
 * which marks all other quotes Rejected). A claim with no accepted quote is
 * counted as "not routed" and excluded from the approved-% numerator/denominator.
 */
class ClaimsIncentiveReportController extends Controller
{
    private const FLAG = 'claims_incentive_report';

    /**
     * GET /api/v1/claims-v2/reports/incentive?date_from=&date_to=
     *
     * Aggregates MOTOR (panel-beater) and GLASS claims over a date range.
     */
    public function report(Request $request): JsonResponse
    {
        if (!IntegrationSettings::isEnabled(self::FLAG, false)) {
            return response()->json(['message' => 'Incentive report is not enabled.'], 403);
        }

        $validated = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
        ]);

        // Default window: current month to today (matches the claims list's
        // created_at date filtering — the only date column guaranteed present).
        $fromDate = $validated['date_from'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $toDate   = $validated['date_to']   ?? Carbon::now()->format('Y-m-d');
        $from = $fromDate . ' 00:00:00';
        $to   = $toDate   . ' 23:59:59';

        $table = $this->claimsTable();

        // One row per (claim x accepted-quote). LEFT JOIN so unrouted claims
        // (no accepted quote) still appear with a NULL supplier_id.
        $rows = DB::table("{$table} as c")
            ->leftJoin('claim_quotes as cq', function ($j) {
                $j->on('cq.claim_id', '=', 'c.id')->where('cq.status', '=', 'Accepted');
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'cq.supplier_id')
            ->whereBetween('c.created_at', [$from, $to])
            ->where(function ($q) {
                // MOTOR* -> panel-beater; GLASS -> glass. Matched case-insensitively
                // against the claim_type codes issued by claimTypes() (MOTORACCIDENT,
                // GLASS). LIKE 'MOTOR%' also catches any motor sub-code.
                $q->whereRaw('UPPER(c.claim_type) LIKE ?', ['MOTOR%'])
                  ->orWhereRaw('UPPER(c.claim_type) = ?', ['GLASS']);
            })
            ->select(
                'c.id',
                'c.claim_type',
                'cq.supplier_id',
                's.is_approved_panel_beater',
                's.is_approved_glass_supplier'
            )
            ->get();

        // Collapse to one fact per claim. A claim should have at most one
        // accepted quote, but if the data has more, prefer a routed row.
        $claims = [];
        foreach ($rows as $r) {
            $ct       = strtoupper(trim((string) ($r->claim_type ?? '')));
            $category = ($ct === 'GLASS') ? 'glass' : 'panel_beater';
            $routed   = $r->supplier_id !== null;
            $approved = $routed && ($category === 'glass'
                ? (int) ($r->is_approved_glass_supplier ?? 0) === 1
                : (int) ($r->is_approved_panel_beater ?? 0) === 1);

            $existing = $claims[$r->id] ?? null;
            if ($existing === null || (!$existing['routed'] && $routed)) {
                $claims[$r->id] = [
                    'category' => $category,
                    'routed'   => $routed,
                    'approved' => $approved,
                ];
            }
        }

        $result = ClaimsIncentiveReport::compute(array_values($claims));

        return response()->json([
            'data' => [
                'dateFrom'    => $fromDate,
                'dateTo'      => $toDate,
                'panelBeater' => $result['panel_beater'],
                'glass'       => $result['glass'],
            ],
        ]);
    }

    /**
     * PATCH /api/v1/suppliers/{id}/incentive-flags
     * Body (either or both): is_approved_panel_beater, is_approved_glass_supplier (boolean).
     *
     * Marks an existing supplier as an approved panel-beater / glass supplier.
     * Only the supplied flags are changed.
     */
    public function markSupplier(Request $request, int $id): JsonResponse
    {
        if (!IntegrationSettings::isEnabled(self::FLAG, false)) {
            return response()->json(['message' => 'Incentive report is not enabled.'], 403);
        }

        $request->validate([
            'is_approved_panel_beater'   => 'nullable|boolean',
            'is_approved_glass_supplier' => 'nullable|boolean',
        ]);

        $supplier = DB::table('suppliers')->where('id', $id)->first();
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $update = [];
        if ($request->has('is_approved_panel_beater')) {
            $update['is_approved_panel_beater'] = (int) $request->boolean('is_approved_panel_beater');
        }
        if ($request->has('is_approved_glass_supplier')) {
            $update['is_approved_glass_supplier'] = (int) $request->boolean('is_approved_glass_supplier');
        }
        if (empty($update)) {
            return response()->json(['message' => 'No incentive flags supplied.'], 422);
        }

        $update['updated_at'] = Carbon::now();
        DB::table('suppliers')->where('id', $id)->update($update);

        return response()->json([
            'message' => 'Supplier incentive flags updated.',
            'data'    => [
                'id'                         => $id,
                'is_approved_panel_beater'   => array_key_exists('is_approved_panel_beater', $update)
                    ? (bool) $update['is_approved_panel_beater'] : (bool) ($supplier->is_approved_panel_beater ?? false),
                'is_approved_glass_supplier' => array_key_exists('is_approved_glass_supplier', $update)
                    ? (bool) $update['is_approved_glass_supplier'] : (bool) ($supplier->is_approved_glass_supplier ?? false),
            ],
        ]);
    }

    /**
     * Return the claims table that actually exists (mirrors ClaimsV2Controller).
     */
    private function claimsTable(): string
    {
        try {
            DB::table('claims')->limit(1)->first();
            return 'claims';
        } catch (\Exception $e) {
            return 'new_claims';
        }
    }
}
