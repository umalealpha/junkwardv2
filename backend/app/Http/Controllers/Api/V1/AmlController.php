<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\OpenSanctionsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Synchronous AML screening for the customer-facing flow.
 *
 * V2 already has WeeklyAmlScreening (cron) + RunOpenSanctionsJob (queue) +
 * OpenSanctionsClient (service) — but no synchronous HTTP endpoint, which
 * means alphaFEV2 (or any front-end) can't block a policy submission on
 * the screening result.
 *
 * This controller wraps OpenSanctionsClient::match() in a request-scoped
 * call that returns a flat boolean verdict the FE can branch on:
 *   { clean: bool, max_score: float, matches: [{name, score, datasets, ...}] }
 *
 * Threshold for "clean = false" is 0.7 (mirrors transformResponse target
 * flag and validateExtractedData confidence). Matches at or above 0.7 are
 * considered hits the operator should review before allowing the policy.
 */
class AmlController extends Controller
{
    private const MATCH_THRESHOLD = 0.7;

    /**
     * POST /api/v1/aml/check
     * Body: { name (required), dob (YYYY-MM-DD), nationality, customer_id }
     * Returns: { clean, max_score, matches[], threshold }
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'dob'         => 'nullable|date_format:Y-m-d',
            'nationality' => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer',
        ]);

        $client = new OpenSanctionsClient();

        try {
            $raw = isset($validated['customer_id'])
                ? $client->screenCustomer(
                    (int) $validated['customer_id'],
                    $validated['name'],
                    $validated['dob'] ?? null,
                    $validated['nationality'] ?? null
                )
                : $client->match(
                    $validated['name'],
                    $validated['dob'] ?? null,
                    $validated['nationality'] ?? null
                );
        } catch (\Throwable $e) {
            // Fail-open is wrong for AML — but fail-closed (block every
            // submission) when the AML service is down would halt the
            // entire onboarding pipeline. Compromise: 502 with explicit
            // message so the FE can retry / route to manual review.
            Log::error('AML sync check failed', [
                'name'  => $validated['name'],
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'AML screening service unavailable. Retry, or send this customer to manual review.',
            ], 502);
        }

        $matches = collect($raw['responses']['q1']['results'] ?? [])
            ->map(fn($m) => [
                'name'      => $m['caption'] ?? null,
                'score'     => (float) ($m['score'] ?? 0),
                'datasets'  => $m['datasets'] ?? [],
                'birthDate' => $m['properties']['birthDate'][0] ?? null,
                'notes'     => $m['properties']['notes'][0] ?? null,
            ])
            ->sortByDesc('score')
            ->values()
            ->all();

        $maxScore = collect($matches)->max('score') ?? 0.0;
        $clean    = $maxScore < self::MATCH_THRESHOLD;

        // Audit trail — same aml_results table the cron uses, so SOC review
        // covers FE-driven checks too. Best-effort; never block on logging.
        try {
            DB::table('aml_results')->insert([
                'customer_id' => $validated['customer_id'] ?? null,
                'query'       => json_encode([
                    'name' => $validated['name'],
                    'dob' => $validated['dob'] ?? null,
                    'nationality' => $validated['nationality'] ?? null,
                    'source' => 'sync_api',
                ]),
                'results'     => json_encode($matches),
                'max_score'   => $maxScore,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('aml_results insert failed: ' . $e->getMessage());
        }

        return response()->json([
            'clean'      => $clean,
            'max_score'  => round($maxScore, 4),
            'threshold'  => self::MATCH_THRESHOLD,
            'matches'    => $matches,
            'message'    => $clean
                ? 'No sanctions hits above threshold.'
                : 'Possible sanctions hit — manual review required before policy issuance.',
        ]);
    }
}
