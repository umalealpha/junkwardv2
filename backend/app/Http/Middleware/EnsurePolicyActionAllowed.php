<?php

namespace AlphaDirect\Http\Middleware;

use AlphaDirect\Services\Validation\PolicyValidationRuleEngine;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a policy action endpoint behind the legacy GFS validation rule engine.
 *
 * Usage on a route:
 *   Route::post('policies/{id}/issue', [Ctrl::class, 'issuePolicy'])
 *       ->middleware('policy.action:canIssue');
 *
 * The middleware:
 *   1. Reads the `{id}` (or `{policyId}`) route parameter
 *   2. Resolves the latest non-deleted policy_actions row for that policy
 *   3. Calls PolicyValidationRuleEngine::evaluateActions(policy, action, user)
 *   4. If the named action is blocked, returns 403 with the rule's screen
 *      error message — UI surfaces it to UW as a one-line denial
 *   5. Otherwise calls $next($request) and the controller runs as usual
 *
 * Action key must be one of PolicyValidationRuleEngine::ACTIONS:
 *   canRate | canPrintQuote | canPrintApp | canBindApp | canSubmitUnbound | canIssue
 *
 * Fail-safe: any internal error (missing policy id, engine throw, etc.)
 * does NOT block the request. The engine itself returns the permissive
 * default on exceptions; this middleware mirrors that posture — bugs
 * here must never block legitimate UW work.
 */
class EnsurePolicyActionAllowed
{
    public function handle(Request $request, Closure $next, string $actionKey): Response
    {
        try {
            if (!in_array($actionKey, PolicyValidationRuleEngine::ACTIONS, true)) {
                Log::warning("EnsurePolicyActionAllowed: unknown actionKey '{$actionKey}' — letting request through");
                return $next($request);
            }

            $policyId = (int) (
                $request->route('id')
                ?? $request->route('policyId')
                ?? $request->route('policy_id')
                ?? 0
            );
            if ($policyId <= 0) {
                return $next($request);
            }

            $actionId = (int) DB::table('policy_actions')
                ->where('policy_id', $policyId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->value('id');

            $userId = optional(auth()->user())->id;

            $result = PolicyValidationRuleEngine::evaluateActions($policyId, $actionId, $userId);

            if (($result['allowed'][$actionKey] ?? true) === false) {
                // Find the most informative blocker for this specific action
                $blockingMessage = $this->firstMessageBlockingAction($result['blockingRules'] ?? [], $actionKey)
                    ?? 'You are not authorised to perform this action. Please see your manager for assistance.';

                return response()->json([
                    'success'        => false,
                    'message'        => $blockingMessage,
                    'blocked_action' => $actionKey,
                    'blocking_rules' => $result['blockingRules'] ?? [],
                ], Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        } catch (\Throwable $e) {
            Log::warning('EnsurePolicyActionAllowed threw — letting request through', [
                'action' => $actionKey,
                'err'    => $e->getMessage(),
            ]);
            return $next($request);
        }
    }

    /**
     * @param  array<int,array{ruleCode:string,message:string,blocks:array<int,string>}>  $rules
     */
    private function firstMessageBlockingAction(array $rules, string $actionKey): ?string
    {
        foreach ($rules as $r) {
            if (in_array($actionKey, $r['blocks'] ?? [], true) && !empty($r['message'])) {
                return $r['message'];
            }
        }
        return null;
    }
}
