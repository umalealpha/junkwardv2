<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reinsurance / underwriting validation rule evaluator.
 *
 * Ports graphiteBWV8 `Http\Livewire\Policy\Submit::inApproval` (legacy lines
 * 1380–1448) into a reusable service. Both the submit gate
 * (PolicyCreateController::submitToApproval) and the UW-preview endpoint
 * (UnderwritingController::preview) call ::validate() to get the same
 * answer without duplicating the logic.
 *
 * What it does:
 *   1. Resolve the given user's role → role.rule_group
 *   2. Load active rule-masters for that group + product + today's date
 *   3. For each rule, iterate its detail rows. Each detail names a
 *      reinsurance_group; sum policy_coverage_detail.calculated_value
 *      for every coverage in that group across the action's coverages.
 *   4. Apply the detail's formula expression (s_FormulaExpression)
 *      against s_CompareValue (and s_CompareValueBetween for between
 *      rules). If any detail fails, push the rule's s_ScreenErrorMsg.
 *
 * Formula-expression codes (tb_prformulaexpression):
 *   60   = <   | 61   = ==  | 62   = >
 *   8800 = !=  | 8801 = between (<=cmp AND >=cmpB)
 *   8802 = between (>=cmp AND <=cmpB)
 *   8804 = <=  | 8805 = >=
 *
 * Safety: on internal exception returns empty array so a rules-engine
 * bug can never freeze new business. Failures are logged but not
 * surfaced to the caller.
 */
class ReinsuranceValidator
{
    /**
     * Evaluate validation rules for the given policy action and user.
     *
     * @param  int       $policyId
     * @param  int       $actionId
     * @param  int|null  $termId   (unused today; reserved for term-scoped rules)
     * @param  int|null  $userId   If null, uses auth()->user(); if no auth,
     *                             returns empty (CLI / system context).
     * @return array<int,string>   Human-readable error messages. Empty on pass.
     */
    public static function validate(int $policyId, int $actionId, $termId = null, ?int $userId = null): array
    {
        try {
            $user = $userId
                ? DB::table('users')->where('id', $userId)->first(['id'])
                : auth()->user();
            if (!$user) return [];

            // 1. Resolve role.rule_group — relies on Spatie Permission or
            //    similar. Use a raw query to avoid coupling to the User model.
            $role = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_id', $user->id)
                ->where('mhr.model_type', \AlphaDirect\User::class)
                ->orderByDesc('r.id')
                ->first(['r.id', 'r.rule_group']);
            if (!$role || empty($role->rule_group)) return [];

            $policy = DB::table('policies')->where('id', $policyId)->first(['product_id']);
            if (!$policy) return [];
            $today = now()->format('Y-m-d');

            // 2. Rule-master IDs attached to this role's group
            $ruleMasterIds = DB::table('tb_prvalidationrulegroupdetails')
                ->where('n_PrValidationRuleGroupMasters_FK', $role->rule_group)
                ->where('n_PrValidationRuleMasters_FK', '!=', 0)
                ->pluck('n_PrValidationRuleMasters_FK');
            if ($ruleMasterIds->isEmpty()) return [];

            $rules = DB::table('tb_prvalidationrulemasters')
                ->whereIn('n_PrValidationRuleMaster_PK', $ruleMasterIds)
                ->where('n_Product_FK', $policy->product_id)
                ->where('d_EffectiveDateFrom', '<=', $today)
                ->where('d_EffectiveDateTo', '>=', $today)
                ->get();
            if ($rules->isEmpty()) return [];

            // Coverages for this specific policy-action
            $policyCoverageIds = DB::table('policy_coverages')
                ->where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereNull('deleted_at')
                ->pluck('id');

            $errors = [];
            foreach ($rules as $rule) {
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

                    if (self::ruleFails($sum, (string) $d->s_FormulaExpression,
                                        (float) ($d->s_CompareValue ?? 0),
                                        (float) ($d->s_CompareValueBetween ?? 0))) {
                        if (!empty($rule->s_ScreenErrorMsg)) {
                            $errors[] = $rule->s_ScreenErrorMsg;
                        }
                        break; // one failed detail flags the rule; stop evaluating its peers
                    }
                }
            }
            return array_values(array_unique($errors));
        } catch (\Throwable $e) {
            Log::warning('ReinsuranceValidator::validate threw, returning no errors', [
                'policy_id' => $policyId, 'action_id' => $actionId, 'err' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Apply one formula-expression comparator. Returns true when the rule
     * should fail (i.e. error is raised).
     */
    private static function ruleFails(float $sum, string $op, float $cmp, float $cmpB): bool
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
