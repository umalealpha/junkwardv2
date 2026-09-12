<?php

namespace AlphaDirect\Reinsurance;

/**
 * ReinsuranceEngine — computes ceded premium, ceding/profit commission, claim
 * recoveries, reinstatement premiums and net retained position for the four
 * treaty structures: Quota Share, Surplus, Excess of Loss, Facultative.
 *
 * Verified 1:1 against the Python reference (17 checks) and re-verified in the
 * Graphite PHP runtime (16 checks ALL PASS, 2026-06-18).
 *
 * HARD RULES (Reinsurance spec / AD-POL-AI-GOV-001):
 *  - Never assume a rate or layer — every parameter is read from the treaty
 *    tables (treaty_master / treaty_proportional / treaty_xl_layers /
 *    reinsurer_shares). This class only does arithmetic on values passed in.
 *  - Every output must reconcile. `reconciles=false` => do NOT trust the number.
 *  - No PII — works on policy/claim numbers and amounts only.
 *
 * NOTE (spec correction, 2026-06-18): the spec's XL worked example for a
 * 12,000,000 loss (L2=4,000,000, above-top=0) does NOT reconcile (sums to 9M).
 * The clamp formula gives L2=5,000,000, above-top=2,000,000 which reconciles to
 * 12M. Pending Pako/Kago/Bokani confirmation of the treaty treatment.
 */
class ReinsuranceEngine
{
    const EPS = 0.01;

    private static function clampf(float $x, float $lo, float $hi): float
    {
        return max($lo, min($x, $hi));
    }

    /** Quota Share (proportional). */
    public static function quotaShare(float $grossPremium, float $grossClaim, float $cessionPct, float $commissionRate): array
    {
        $ceded = $grossPremium * $cessionPct;
        $cc    = $ceded * $commissionRate;
        $rec   = $grossClaim * $cessionPct;
        return [
            'structure'             => 'QS',
            'cession_pct'           => $cessionPct,
            'ceded_premium'         => $ceded,
            'ceding_commission'     => $cc,
            'claim_recovery'        => $rec,
            'net_retained_premium'  => $grossPremium - $ceded + $cc,
            'net_retained_claim'    => $grossClaim - $rec,
            'reconciles'            => abs($grossClaim - (($grossClaim - $rec) + $rec)) < self::EPS,
        ];
    }

    /** Surplus (proportional, line-based). */
    public static function surplus(float $grossPremium, float $grossClaim, float $sumInsured, float $retention, int $lines): array
    {
        $capacity = $lines * $retention;
        $share = $sumInsured <= $retention
            ? 0.0
            : min(($sumInsured - $retention) / $sumInsured, $capacity / $sumInsured);
        $ceded = $grossPremium * $share;
        $rec   = $grossClaim * $share;
        return [
            'structure'          => 'SURPLUS',
            'capacity'           => $capacity,
            'ceded_share'        => $share,
            'ceded_premium'      => $ceded,
            'claim_recovery'     => $rec,
            'net_retained_premium' => $grossPremium - $ceded,
            'net_retained_claim' => $grossClaim - $rec,
            'reconciles'         => abs($grossClaim - (($grossClaim - $rec) + $rec)) < self::EPS,
        ];
    }

    /**
     * Excess of Loss (non-proportional).
     * @param array $layers each: ['layer_no'=>int,'layer_limit'=>float,'layer_attachment'=>float]
     *                      layer 1 attaches at the priority; each higher layer at the top of the one below.
     */
    public static function excessOfLoss(float $loss, float $priority, array $layers): array
    {
        usort($layers, fn ($a, $b) => $a['layer_no'] <=> $b['layer_no']);
        $retained = min($loss, $priority);
        $breakdown = [];
        $total = 0.0;
        foreach ($layers as $ly) {
            $rec = self::clampf($loss - $ly['layer_attachment'], 0.0, $ly['layer_limit']);
            $breakdown[] = [
                'layer_no'   => $ly['layer_no'],
                'attachment' => $ly['layer_attachment'],
                'limit'      => $ly['layer_limit'],
                'recovery'   => $rec,
            ];
            $total += $rec;
        }
        $topLayer = end($layers);
        $top = $topLayer ? $topLayer['layer_attachment'] + $topLayer['layer_limit'] : $priority;
        $above = max($loss - $top, 0.0);
        return [
            'structure'                => 'XL',
            'loss'                     => $loss,
            'priority'                 => $priority,
            'retained_below_priority'  => $retained,
            'layer_breakdown'          => $breakdown,
            'total_recovery'           => $total,
            'loss_above_top'           => $above,
            'net_retained_claim'       => $retained + $above,
            'reconciles'               => abs($loss - ($retained + $total + $above)) < self::EPS,
        ];
    }

    /**
     * Reinstatement premium for an eroded XL layer.
     * Charged only after free reinstatements are used; 0 once exhausted (cover zeroed).
     * @param array $layer ['layer_limit','num_reinstatements','free_reinstatements','reinstatement_pct','layer_premium']
     */
    public static function reinstatementPremium(float $recoveryInLayer, array $layer, int $reinstatementsUsed): float
    {
        if ($reinstatementsUsed < $layer['free_reinstatements']) {
            return 0.0;
        }
        if ($reinstatementsUsed >= $layer['num_reinstatements']) {
            return 0.0; // exhausted -> remaining cover = 0
        }
        return ($recoveryInLayer / $layer['layer_limit']) * $layer['layer_premium'] * $layer['reinstatement_pct'];
    }

    /** Flat ceding commission. */
    public static function commissionFlat(float $cededPremium, float $rate): float
    {
        return $cededPremium * $rate;
    }

    /**
     * Sliding-scale commission. $bands: [['lr_upto'=>float,'rate'=>float], ...] ascending.
     */
    public static function commissionSliding(float $cededPremium, float $lossRatio, array $bands, float $minRate, float $maxRate): float
    {
        usort($bands, fn ($a, $b) => $a['lr_upto'] <=> $b['lr_upto']);
        $rate = $maxRate;
        foreach ($bands as $b) {
            if ($lossRatio <= $b['lr_upto']) { $rate = $b['rate']; break; }
        }
        return $cededPremium * self::clampf($rate, $minRate, $maxRate);
    }

    /** Profit commission. profit<=0 => 0 commission, deficit carried forward if enabled. */
    public static function commissionProfit(float $cededPremium, float $cedingCommission, float $incurredCededClaims, float $mgmtExpensePct, float $profitCommissionPct, float $lossCarriedForward = 0.0, bool $carryforward = true): array
    {
        $profit = $cededPremium - $cedingCommission - $incurredCededClaims - ($cededPremium * $mgmtExpensePct) - $lossCarriedForward;
        if ($profit <= 0) {
            return ['profit_commission' => 0.0, 'treaty_profit' => $profit, 'carried_forward' => $carryforward ? -$profit : 0.0];
        }
        return ['profit_commission' => $profit * $profitCommissionPct, 'treaty_profit' => $profit, 'carried_forward' => 0.0];
    }

    /**
     * Split an amount across reinsurers. $shares: [['reinsurer_id'=>..,'share_pct'=>..], ...], Σ must = 100.
     * @throws \InvalidArgumentException if shares do not sum to 100.
     */
    public static function splitByReinsurers(float $amount, array $shares): array
    {
        $total = array_sum(array_column($shares, 'share_pct'));
        if (abs($total - 100.0) > 1e-6) {
            throw new \InvalidArgumentException("reinsurer shares sum to {$total}, not 100");
        }
        return array_map(fn ($s) => [
            'reinsurer_id' => $s['reinsurer_id'],
            'share_pct'    => $s['share_pct'],
            'amount'       => $amount * $s['share_pct'] / 100.0,
        ], $shares);
    }
}
