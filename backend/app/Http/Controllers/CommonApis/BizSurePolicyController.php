<?php

namespace AlphaDirect\Http\Controllers\CommonApis;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\BizSureOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BizSure-specific policy creation endpoint, kept isolated from V2's
 * existing CommonApis/PolicyController (which handles the LiveQuote flow
 * and hardcodes leadSource='LiveQuote' on line 332).
 *
 * Mirrors graphiteBWV8 CommonApis/PolicyController::createPolicy (1,892
 * lines, 6 sub-handlers). This file currently exposes the entry-point
 * validation only — the full sub-handler chain is being ported in
 * follow-up commits:
 *
 *   1. Policy number generation (COMD prefix for BizSure vs MIS for others)
 *   2. Customer profile + business entity resolution (Person vs Pty Ltd)
 *   3. PolicyTerm + PolicyAction hierarchy creation
 *   4. Risk address insertion + state/city FK resolution
 *   5. Multi-level policy_coverages tree (parent / sub / extensions)
 *   6. Portable items[] flattening into policy_specified_items
 *   7. Directors[] persistence into policy_directors
 *   8. Vehicles[] persistence + policy_kyc_documents linking
 *   9. RealPay instant activation hook + PDF dispatch
 *
 * URL: POST /api/bizsure/createPolicy
 * (V1 path was /api/createPolicy — moved under /bizsure/ to avoid
 * conflicting with V2's existing LiveQuote createPolicy at /api/createPolicy.
 * BizSure client team will update endpoint URL during cutover.)
 */
class BizSurePolicyController extends Controller
{
    public function __construct(private BizSureOrchestrator $orchestrator)
    {
    }

    /**
     * Entry-point validation + leadSource gate, then delegate to the
     * BizSureOrchestrator. The orchestrator is being built up over six
     * commits (Phase 2c); the response `phase` field tells callers how
     * far along the chain the run got — useful while the chain is
     * still being assembled.
     */
    public function createPolicy(Request $request): JsonResponse
    {
        $leadSource = (string) $request->input('leadSource', '');

        if (strtolower($leadSource) !== 'bizsure') {
            return response()->json([
                'success' => false,
                'error'   => "BizSure createPolicy requires leadSource='bizsure'.",
            ], 400);
        }

        $required = [
            'product',          // BizSure product id (COMD prefix family)
            'firstname',
            'lastname',
            'phone',
            'email',
            'omang',
        ];
        foreach ($required as $field) {
            if (trim((string) $request->input($field, '')) === '') {
                return response()->json([
                    'success' => false,
                    'error'   => "Missing required field '{$field}'.",
                ], 400);
            }
        }

        // Business structure gates the directors[] expectation.
        $businessStructure = strtolower((string) $request->input('business_structure', ''));
        if (! in_array($businessStructure, ['sole_proprietor', 'company'], true)) {
            return response()->json([
                'success' => false,
                'error'   => "business_structure must be 'sole_proprietor' or 'company'.",
            ], 400);
        }

        if ($businessStructure === 'company') {
            $directors = $request->input('directors', []);
            if (! is_array($directors) || count($directors) === 0) {
                return response()->json([
                    'success' => false,
                    'error'   => "directors[] is required for business_structure='company'.",
                ], 400);
            }
        }

        // Correlation id ties the partner's 500 response to our server logs
        // without leaking any internal detail (or PII) onto the wire.
        $correlationId = uniqid('bzs_', true);

        // NOTE: never log customer PII (firstname/omang/email/phone) here —
        // DPA / AD-POL-AI-GOV-001. Only non-identifying shape + counts.
        Log::info('[BizSurePolicyController] createPolicy entry validated', [
            'correlation_id'     => $correlationId,
            'leadSource'         => $leadSource,
            'business_structure' => $businessStructure,
            'product'            => $request->input('product'),
            'directors_count'    => count((array) $request->input('directors', [])),
            'covers_count'       => count((array) $request->input('covers', [])),
            'vehicles_count'     => count((array) $request->input('vehicles', [])),
        ]);

        try {
            $result = $this->orchestrator->createPolicy($request->all());

            // The BizSure client reads the V1 response shape: response.policy.{id,
            // policyNumber, customer_id, status, premium}. The orchestrator returns
            // those as flat keys, so expose a nested `policy` object too (mirrors
            // V1's `'policy' => $policy`). Flat keys are kept for forward-compat.
            $nestedPolicy = [
                'id'           => $result['policy_id']     ?? null,
                'policyNumber' => $result['policy_number'] ?? null,
                'customer_id'  => $result['customer_id']   ?? null,
                'status'       => $result['policy_status'] ?? null,
                'premium'      => $result['total_premium'] ?? null,
            ];

            return response()->json(array_merge(
                ['success' => true, 'policy' => $nestedPolicy],
                $result
            ), 200);
        } catch (\Throwable $e) {
            Log::error('[BizSurePolicyController] orchestrator threw', [
                'correlation_id' => $correlationId,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            // Do NOT return $e->getMessage() — DB exceptions echo the failing
            // SQL with bound customer PII. Return only the correlation id so
            // ops can find the full detail in the logs.
            return response()->json([
                'success'        => false,
                'error'          => 'Internal error during BizSure createPolicy.',
                'correlation_id' => $correlationId,
            ], 500);
        }
    }

    /**
     * GET /api/bizsure/policy-expiry
     *
     * Returns policy expiry dates for BizSure's renewals engine.
     * BizSure can poll daily or call on-demand.
     *
     * Query params (all optional — combine to narrow results):
     *   agent_code  string   filter by agent code (BizSure agent id)
     *   cover_type  string   filter by coverage code e.g. COMD, MOTORP
     *   days_ahead  int      only return policies expiring within N days (default 90)
     *   page        int      pagination page (default 1)
     *   per_page    int      results per page (default 100, max 500)
     *
     * Response shape:
     *   { success: true, total: int, page: int, per_page: int, data: [...] }
     *   data[]: { policy_number, client_name, agent_code, cover_type, expiry_date }
     */
    public function policyExpiry(Request $request): JsonResponse
    {
        $daysAhead = min((int) $request->input('days_ahead', 90), 365);
        $perPage   = min((int) $request->input('per_page', 100), 500);
        $page      = max((int) $request->input('page', 1), 1);
        $agentCode = $request->input('agent_code');
        $coverType = $request->input('cover_type');

        try {
            $query = DB::table('policies as p')
                ->join('policy_term as pt', 'pt.policy_id', '=', 'p.id')
                ->join('policy_coverages as pc', 'pc.policy_id', '=', 'p.id')
                ->join('tb_cvgpccoverages as cm', 'cm.id', '=', 'pc.coverage_id')
                ->join('customer as c', 'c.id', '=', 'p.customer_id')
                ->whereNull('pc.deleted_at')
                ->where('p.leadSource', 'bizsure')
                ->whereDate('pt.term_end_date', '>=', now()->toDateString())
                ->whereDate('pt.term_end_date', '<=', now()->addDays($daysAhead)->toDateString())
                ->select([
                    'p.policyNumber as policy_number',
                    DB::raw("TRIM(CONCAT(c.firstName, ' ', COALESCE(c.middleName,''), ' ', c.lastName)) as client_name"),
                    'p.agent_id as agent_code',
                    'cm.s_CoverageCode as cover_type',
                    DB::raw('DATE(pt.term_end_date) as expiry_date'),
                ])
                ->distinct();

            if ($agentCode) {
                $query->where('p.agent_id', $agentCode);
            }
            if ($coverType) {
                $query->where('cm.s_CoverageCode', strtoupper($coverType));
            }

            $total   = $query->count();
            $results = $query->orderBy('pt.term_end_date')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success'  => true,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'data'     => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[BizSurePolicyController] policyExpiry error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to retrieve policy expiry data.'], 500);
        }
    }
}
