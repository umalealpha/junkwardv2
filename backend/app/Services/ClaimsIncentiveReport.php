<?php

namespace AlphaDirect\Services;

/**
 * ClaimsIncentiveReport — pure aggregation for the approved panel-beater /
 * approved glass-supplier incentive KPI (ported from the Claims Tracker).
 *
 * The controller (ClaimsIncentiveReportController) does the DB work: it selects
 * the MOTOR + GLASS claims in a date range, joins each claim's ACCEPTED quote to
 * the supplier that handled it, and reads that supplier's approved flag. It then
 * hands this class a flat list of already-resolved per-claim facts. Keeping the
 * arithmetic here (no DB, no framework) makes the KPI unit-testable.
 *
 * Each input claim is:
 *   ['category' => 'panel_beater' | 'glass', 'routed' => bool, 'approved' => bool]
 *
 *   - category : 'panel_beater' for MOTOR claims, 'glass' for GLASS claims.
 *   - routed   : true when the claim was routed to a supplier (has an accepted
 *                quote with a supplier). The incentive % is measured against
 *                routed claims — an unrouted claim was never sent anywhere.
 *   - approved : true when the handling supplier carries the matching approved
 *                flag (approved panel-beater for motor, approved glass supplier
 *                for glass). Always false when not routed.
 */
class ClaimsIncentiveReport
{
    /**
     * @param  array<int,array{category:string,routed:bool,approved:bool}> $claims
     * @return array{panel_beater:array,glass:array}
     */
    public static function compute(array $claims): array
    {
        $buckets = [
            'panel_beater' => self::emptyBucket(),
            'glass'        => self::emptyBucket(),
        ];

        foreach ($claims as $c) {
            $cat = $c['category'] ?? null;
            if (!isset($buckets[$cat])) {
                // Ignore anything that isn't one of the two incentive categories.
                continue;
            }

            $routed   = !empty($c['routed']);
            $approved = $routed && !empty($c['approved']);

            $buckets[$cat]['total']++;
            if ($routed) {
                $buckets[$cat]['routed']++;
                if ($approved) {
                    $buckets[$cat]['routedToApproved']++;
                } else {
                    $buckets[$cat]['routedToNonApproved']++;
                }
            } else {
                $buckets[$cat]['notRouted']++;
            }
        }

        foreach ($buckets as $cat => $b) {
            $buckets[$cat]['approvedPctOfRouted'] = self::pct($b['routedToApproved'], $b['routed']);
            $buckets[$cat]['routedPctOfTotal']    = self::pct($b['routed'], $b['total']);
        }

        return $buckets;
    }

    /**
     * Percentage of $part out of $whole, rounded to 2 dp. 0.0 when $whole is 0
     * (never divides by zero — an empty category reports 0%, not an error).
     */
    public static function pct(int $part, int $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }
        return round(($part / $whole) * 100, 2);
    }

    private static function emptyBucket(): array
    {
        return [
            'total'               => 0,
            'routed'              => 0,
            'notRouted'           => 0,
            'routedToApproved'    => 0,
            'routedToNonApproved' => 0,
            'approvedPctOfRouted' => 0.0,
            'routedPctOfTotal'    => 0.0,
        ];
    }
}
