<?php

namespace AlphaDirect\Services\Validation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action-aware validation rule engine.
 *
 * Reads the legacy GFS validation rule tables (tb_prvalidationrulemasters,
 * tb_prvalidationruledetails, tb_prvalidationrulegroupmasters,
 * tb_prvalidationrulegroupdetails) and returns the 6-action permission map
 * for a (policy, action, user) tuple.
 *
 * This is the additive companion to
 * AlphaDirect\Services\Reinsurance\ReinsuranceValidator (kept in place
 * unchanged for the Submit-for-Approval gate). The Reinsurance validator
 * returns text error messages; this engine returns a boolean per-action
 * map so the frontend can pre-emptively grey out buttons and so each
 * action endpoint can gate itself.
 *
 * Failure safety: on any internal error the engine returns a permissive
 * default (all actions allowed, no blockers). A bug in this engine must
 * never block legitimate underwriting work.
 *
 * Caching: lookups are point-in-time and the underlying data changes
 * rarely (rule edits are admin-only). Callers that need to repeat the
 * call inside a single HTTP request should memoise the result.
 */
class PolicyValidationRuleEngine
{
    /** All 6 GFS action keys in canonical order. */
    public const ACTIONS = [
        'canRate',
        'canPrintQuote',
        'canPrintApp',
        'canBindApp',
        'canSubmitUnbound',
        'canIssue',
    ];

    /** Map GFS canonical action key → DB column on tb_prvalidationrulemasters. */
    private const ACTION_COLUMN_MAP = [
        'canRate'           => 's_CanRate',
        'canPrintQuote'     => 's_CanPrintQuote',
        'canPrintApp'       => 's_CanPrintApp',
        'canBindApp'        => 's_CanBindApp',
        'canSubmitUnbound'  => 's_CanUnBoundApp',
        'canIssue'          => 's_CanIssue',
    ];

    /**
     * Evaluate validation rules for a given policy + action + user, returning
     * the 6-action permission map plus the list of rules that block actions.
     *
     * Shape:
     *   [
     *     'allowed' => [
     *       'canRate' => true|false,
     *       'canPrintQuote' => true|false,
     *       'canPrintApp' => true|false,
     *       'canBindApp' => true|false,
     *       'canSubmitUnbound' => true|false,
     *       'canIssue' => true|false,
     *     ],
     *     'blockingRules' => [
     *       ['ruleCode' => '...', 'message' => '...', 'blocks' => ['canIssue', ...]],
     *       ...
     *     ],
     *   ]
     *
     * @param  int       $policyId
     * @param  int       $actionId
     * @param  int|null  $userId   If null, uses auth()->user(); if no auth,
     *                             returns the permissive default.
     */
    public static function evaluateActions(int $policyId, int $actionId, ?int $userId = null): array
    {
        $result = self::evaluateLegacyRules($policyId, $actionId, $userId);

        // Product-specific gates that live in code rather than in the legacy
        // GFS rule tables are merged in here, in the same shape, so every
        // consumer (route middleware, Policy Detail gate preview) picks them
        // up with no extra plumbing.
        //
        // Bonds (product 23): EXCO approval + confirmed collateral before
        // issue. See AlphaDirect\Services\Bonds\BondsIssuanceGate.
        $extra = \AlphaDirect\Services\Bonds\BondsIssuanceGate::blockingRules($policyId, $actionId);
        foreach ($extra as $rule) {
            foreach ($rule['blocks'] ?? [] as $actionKey) {
                if (array_key_exists($actionKey, $result['allowed'])) {
                    $result['allowed'][$actionKey] = false;
                }
            }
            $result['blockingRules'][] = $rule;
        }

        return $result;
    }

    /**
     * The legacy GFS rule-table evaluation — everything documented on
     * evaluateActions() above. Kept separate so code-level product gates can
     * be layered on top without touching its many fail-safe early returns.
     */
    private static function evaluateLegacyRules(int $policyId, int $actionId, ?int $userId = null): array
    {
        $permissive = [
            'allowed'       => array_fill_keys(self::ACTIONS, true),
            'blockingRules' => [],
        ];

        try {
            // 1. Resolve user → role → role.rule_group
            $user = $userId
                ? DB::table('users')->where('id', $userId)->first(['id'])
                : auth()->user();
            if (!$user) return $permissive;

            // Users may carry multiple roles. Prefer the one with a non-empty
            // rule_group (the "grade" role assigned by the user-mappings
            // importer). If none of the user's roles carry a rule_group,
            // the engine returns the permissive default — fail-safe.
            $role = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_id', $user->id)
                ->where('mhr.model_type', \AlphaDirect\User::class)
                ->whereNotNull('r.rule_group')
                ->where('r.rule_group', '!=', '')
                ->orderByDesc('r.id')
                ->first(['r.id', 'r.rule_group']);
            if (!$role || empty($role->rule_group)) return $permissive;

            // 2. Resolve policy → product
            $policy = DB::table('policies')->where('id', $policyId)->first(['product_id']);
            if (!$policy) return $permissive;

            // 3. Rule-master IDs attached to this role's group
            $today = now()->format('Y-m-d');
            $ruleMasterIds = DB::table('tb_prvalidationrulegroupdetails')
                ->where('n_PrValidationRuleGroupMasters_FK', $role->rule_group)
                ->where('n_PrValidationRuleMasters_FK', '!=', 0)
                ->whereNotNull('n_PrValidationRuleMasters_FK')
                ->pluck('n_PrValidationRuleMasters_FK')
                ->unique();
            if ($ruleMasterIds->isEmpty()) return $permissive;

            // 4. Load active master rules in this group + product + today's window
            $rules = DB::table('tb_prvalidationrulemasters')
                ->whereIn('n_PrValidationRuleMaster_PK', $ruleMasterIds)
                ->where('n_Product_FK', $policy->product_id)
                ->where('d_EffectiveDateFrom', '<=', $today)
                ->where('d_EffectiveDateTo', '>=', $today)
                ->whereIn('s_RuleStatus', ['ACTIVE', 'active'])  // tolerate lowercase legacy data
                ->get();
            if ($rules->isEmpty()) return $permissive;

            // 5. Coverages for this specific policy-action (basis for sum calc)
            $policyCoverageIds = DB::table('policy_coverages')
                ->where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereNull('deleted_at')
                ->pluck('id');

            // 6. Evaluate each rule. If a rule "fires" (threshold breached),
            //    intersect its 6 action flags into the cumulative allowed map.
            $allowed = array_fill_keys(self::ACTIONS, true);
            $blockingRules = [];

            foreach ($rules as $rule) {
                $ruleFires = self::ruleFiresForPolicy($rule, $policyCoverageIds);
                if (!$ruleFires) {
                    continue;
                }

                $blocked = [];
                foreach (self::ACTION_COLUMN_MAP as $actionKey => $column) {
                    $value = strtoupper((string) ($rule->{$column} ?? ''));
                    // V2 schema is enum('Y','N'); legacy GFS exports sometimes
                    // carry 'NO'/'YES'. Accept both 1-char and multi-char forms.
                    // Empty / null / unrecognised → allow (fail-safe).
                    if ($value === 'N' || $value === 'NO') {
                        $allowed[$actionKey] = false;
                        $blocked[] = $actionKey;
                    }
                }

                if (!empty($blocked)) {
                    $blockingRules[] = [
                        'ruleCode' => (string) ($rule->s_RuleCode ?? ''),
                        'message'  => (string) ($rule->s_ScreenErrorMsg ?? ''),
                        'blocks'   => $blocked,
                    ];
                }
            }

            return ['allowed' => $allowed, 'blockingRules' => $blockingRules];
        } catch (\Throwable $e) {
            Log::warning('PolicyValidationRuleEngine::evaluateActions threw — returning permissive default', [
                'policy_id' => $policyId,
                'action_id' => $actionId,
                'err'       => $e->getMessage(),
            ]);
            return $permissive;
        }
    }

    /**
     * Walk a rule's detail rows. Each detail names a reinsurance group;
     * sum policy_coverage_detail.calculated_value across that group and
     * apply the detail's formula expression. Rule fires on first failing
     * detail.
     */
    private static function ruleFiresForPolicy(object $rule, \Illuminate\Support\Collection $policyCoverageIds): bool
    {
        $details = DB::table('tb_prvalidationruledetails')
            ->where('n_PrValidationRuleMaster_FK', $rule->n_PrValidationRuleMaster_PK)
            ->get();

        foreach ($details as $d) {
            $groupCoverageIds = DB::table('reinsurance_group_coverage')
                ->where('group_id', $d->n_PrValidationCodeMasters_FK)
                ->pluck('coverage_id');
            if ($groupCoverageIds->isEmpty()) continue;

            $sum = (float) DB::table('policy_coverage_detail')
                ->whereIn('policy_coverage_id', $policyCoverageIds)
                ->whereIn('coverage_id', $groupCoverageIds)
                ->whereNull('deleted_at')
                ->sum('calculated_value');

            if (self::ruleFires(
                $sum,
                (string) $d->s_FormulaExpression,
                (float) ($d->s_CompareValue ?? 0),
                (float) ($d->s_CompareValueBetween ?? 0)
            )) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply one formula-expression comparator. Mirrors
     * ReinsuranceValidator::ruleFails — kept in sync intentionally.
     *
     * Formula codes:
     *   60   = <      | 61   = ==     | 62   = >
     *   8800 = !=     | 8801 = between(<=cmp AND >=cmpB)
     *   8802 = between(>=cmp AND <=cmpB)
     *   8804 = <=     | 8805 = >=
     *
     * Returns true when the rule SHOULD fire (i.e. condition is met).
     */
    public static function ruleFires(float $sum, string $op, float $cmp, float $cmpB): bool
    {
        return match ($op) {
            '60'   => $sum <  $cmp,
            '61'   => $sum == $cmp,
            '62'   => $sum >  $cmp,
            '8800' => $sum != $cmp,
            '8801' => ($sum <= $cmp && $sum >= $cmpB),
            '8802' => ($sum >= $cmp && $sum <= $cmpB),
            '8804' => $sum <= $cmp,
            '8805' => $sum >= $cmp,
            default => false,
        };
    }
}
