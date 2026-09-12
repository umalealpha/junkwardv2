<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Facades\DB;

/**
 * "Is this policy FAC'ed?"
 *
 * REQUIRED used to be read from `policy_reinsurance` rows carrying a facultative
 * reinsurance type (5 Facultative, 6 Facultative Placement). Those rows only
 * exist where the treaty programme itself has facultative layers attached.
 *
 * The 2024/25 treaty had sixteen such formulas and produced 1,662 rows across 38
 * policies, so that worked. The 2026/27 treaties have none — correctly, because
 * Capacities Notes 2 and 3 state neither treaty establishes a facultative
 * facility. The required side therefore read ZERO on every policy from 1 July
 * 2026, and reported "no facultative required" rather than "cannot assess". On
 * COMG2026213751 that meant nil against P2,636,094,212 sitting outside the
 * treaty.
 *
 * So REQUIRED is now DERIVED — the sum insured the treaty did not absorb:
 *
 *      outside treaty = risk sum insured − (retention + cession + any layer)
 *
 * per risk unit, which is (group, coverage) so a multi-vehicle motor class is
 * measured per vehicle. That figure is independent of whether the treaty happens
 * to carry facultative layers, so it survives a treaty-year restructure.
 *
 * The gap is measured in SUM INSURED, not premium. The question is whether the
 * whole exposure is covered; premium says what was paid, not what is at risk.
 * Premium figures are still returned for the settlement view.
 *
 * NOT everything outside the treaty must be placed facultatively — some is
 * properly retained net. Company standard BR-STD-01 makes placement mandatory
 * only above BWP 50,000,000 sum insured, so a shortfall on a risk above that
 * threshold is an exception; below it, it is reported but not escalated.
 */
class FacCoverageService
{
    /**
     * Coverage position for a single policy.
     *
     * @return array{
     *   policyId:int|null, policyNumber:string, requiresFac:bool,
     *   requiredPremium:float, requiredSumInsured:float,
     *   placedPremium:float, placedSumInsured:float, placedCount:int,
     *   gap:float, gapPremium:float, verdict:string, detail:array
     * }
     */
    public function forPolicy(string $policyNumber): array
    {
        $policyNumber = trim($policyNumber);

        // `policies` has NO deleted_at column — verified against prod 31 Jul 2026.
        // Filtering on it throws. Soft deletes live on policy_actions,
        // policy_reinsurance and policy_ledger, not on policies.
        $policy = DB::table('policies')
            ->where('policyNumber', $policyNumber)
            ->orderByDesc('id')
            ->select('id', 'status')
            ->first();

        $required = $policy
            ? $this->requiredFacFor((int) $policy->id)
            : ['premium' => 0.0, 'sumInsured' => 0.0, 'mandatorySumInsured' => 0.0,
               'rows' => 0, 'actionId' => null, 'byGroup' => []];

        $placed = $this->placedFacFor($policyNumber);

        return $this->assemble($policyNumber, $policy?->id, $required, $placed);
    }

    /**
     * What must be laid off on a policy: the sum insured its treaty could not
     * absorb, from the LATEST reinsurance computation only. Older actions are
     * superseded re-rates; summing across them multiplies the exposure.
     */
    public function requiredFacFor(int $policyId): array
    {
        $latestActionId = DB::table('policy_reinsurance')
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->max('action_id');

        if (!$latestActionId) {
            return ['premium' => 0.0, 'sumInsured' => 0.0, 'mandatorySumInsured' => 0.0,
                    'rows' => 0, 'actionId' => null, 'byGroup' => []];
        }

        return $this->shortfallFor($policyId, (int) $latestActionId);
    }

    /**
     * The treaty shortfall per risk unit.
     *
     * A unit is (group, coverage): motor cedes one cession per vehicle, so
     * grouping on the class alone would collapse two vehicles into one and
     * understate the shortfall. `totalSumInsured` repeats across the retention
     * and cession rows of a unit, hence MAX; the layers are summed.
     */
    public function shortfallFor(int $policyId, int $actionId): array
    {
        // THE RISK UNITS COME FROM THE SEAM, so this follows whichever cession
        // basis is live instead of being wired to one table. On the legacy basis
        // it is the same query that was here, moved not rewritten.
        $units = app(CessionSource::class)
            ->riskUnitsQuery($policyId, $actionId)
            ->get();

        $mandatoryAbove = (float) config('fac.coverage.mandatory_si_threshold', 50000000.0);

        $totalSi = $totalPrm = $mandatorySi = 0.0;
        $overCededSi = 0.0;
        $overCededUnits = 0;
        $overCededGroups = [];
        $byGroup = [];

        foreach ($units as $u) {
            $outSi  = round((float) $u->risk_si - (float) $u->treaty_si, 2);
            $outPrm = round((float) $u->risk_premium - (float) $u->treaty_premium, 2);

            /*
             * A treaty cannot absorb more than the risk, and where it appears to
             * the excess must NOT net off a genuine shortfall elsewhere on the
             * policy. Flooring at nil was right for that.
             *
             * WHAT IT DID NOT DO IS SURFACE IT. The comment here said "surface
             * it" and nothing did: an over-ceded unit simply vanished into a nil
             * shortfall and the policy reported as fully covered. The book run
             * found the live case -- COMG2026213718 cedes 820,544 against a sum
             * insured of 410,272, and this service showed nothing outside the
             * treaty. That is the one condition where "no facultative required"
             * is the least trustworthy answer the check can give.
             *
             * So it still floors, and now it also counts.
             */
            if ($outSi < 0) {
                $overCededSi += -$outSi;
                $overCededUnits++;
                $overCededGroups[(int) $u->group_id] = true;
                $outSi = 0.0;
            }

            $totalSi  += $outSi;
            $totalPrm += max($outPrm, 0.0);
            if ((float) $u->risk_si > $mandatoryAbove) {
                $mandatorySi += $outSi;
            }

            $key = (int) $u->group_id;
            $byGroup[$key]['groupId']    = (int) $u->group_id;
            $byGroup[$key]['groupCode']  = $u->group_code ?? ('group ' . $u->group_id);
            $byGroup[$key]['riskSi']     = round(($byGroup[$key]['riskSi']     ?? 0) + (float) $u->risk_si, 2);
            $byGroup[$key]['treatySi']   = round(($byGroup[$key]['treatySi']   ?? 0) + (float) $u->treaty_si, 2);
            $byGroup[$key]['outsideSi']  = round(($byGroup[$key]['outsideSi']  ?? 0) + $outSi, 2);
            $byGroup[$key]['outsidePrm'] = round(($byGroup[$key]['outsidePrm'] ?? 0) + max($outPrm, 0.0), 2);
            $byGroup[$key]['mandatory']  = ($byGroup[$key]['mandatory'] ?? false)
                || (float) $u->risk_si > $mandatoryAbove;
            $byGroup[$key]['overCeded']  = isset($overCededGroups[$key]);
        }

        return [
            'premium'             => round($totalPrm, 2),
            'sumInsured'          => round($totalSi, 2),
            'mandatorySumInsured' => round($mandatorySi, 2),
            'rows'                => $units->count(),
            'actionId'            => $actionId,
            'byGroup'             => array_values($byGroup),

            // Over-cession is not a shortfall and must not be added to one. It
            // is reported beside it so "nothing outside the treaty" can be told
            // apart from "the cession on this policy does not make sense".
            'overCededSumInsured' => round($overCededSi, 2),
            'overCededUnits'      => $overCededUnits,
        ];
    }

    /**
     * What the FAC register actually carries for a policy.
     *
     * Cancellations and their reversal rows net themselves out because the
     * reversal is stored with negated amounts — the same way the master sheet
     * does it, which is why the June payable reconciles.
     */
    public function placedFacFor(string $policyNumber): array
    {
        $row = DB::table('fac_placements')
            ->where('policy_number', $policyNumber)
            ->whereNull('deleted_at')
            ->select(
                // Compared against Graphite's required premium, which is in PULA —
                // so a foreign line has to be converted, not summed at face value.
                DB::raw("SUM(CASE WHEN currency = 'BWP' THEN COALESCE(gross_ceded_premium_excl_vat,0)"
                    . " ELSE COALESCE(gross_ceded_premium_bwp,0) END) as premium_excl_vat"),
                DB::raw("SUM(CASE WHEN status <> 'cancelled' THEN COALESCE(cession_sum_insured,0) ELSE 0 END) as sum_insured"),
                DB::raw("SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END) as live_count"),
                DB::raw('COUNT(*) as total_count'),
                // A foreign line with no rate has no Pula value, so this policy's
                // premium position cannot be assessed — say so rather than understate it.
                DB::raw("SUM(CASE WHEN currency <> 'BWP' AND fx_rate IS NULL THEN 1 ELSE 0 END) as rate_missing"),
                // Sum insured placed against no reinsurance group cannot be
                // attributed to a class. Reported separately rather than silently
                // credited to the wrong shortfall.
                DB::raw("SUM(CASE WHEN status <> 'cancelled' AND reinsurance_group_id IS NULL"
                    . " THEN COALESCE(cession_sum_insured,0) ELSE 0 END) as unallocated_si")
            )
            ->first();

        $byGroup = DB::table('fac_placements')
            ->where('policy_number', $policyNumber)
            ->whereNull('deleted_at')
            ->where('status', '<>', 'cancelled')
            ->whereNotNull('reinsurance_group_id')
            ->groupBy('reinsurance_group_id')
            ->select(
                'reinsurance_group_id',
                DB::raw('SUM(COALESCE(cession_sum_insured,0)) as placed_si'),
                // `lines` is reserved in MariaDB.
                DB::raw('COUNT(*) as line_count')
            )
            ->get()
            ->keyBy('reinsurance_group_id');

        return [
            'rateMissing'    => (int) ($row->rate_missing ?? 0),
            'premiumExclVat' => round((float) ($row->premium_excl_vat ?? 0), 2),
            'sumInsured'     => round((float) ($row->sum_insured ?? 0), 2),
            'unallocatedSi'  => round((float) ($row->unallocated_si ?? 0), 2),
            'liveCount'      => (int) ($row->live_count ?? 0),
            'totalCount'     => (int) ($row->total_count ?? 0),
            'byGroup'        => $byGroup,
        ];
    }

    private function assemble(string $policyNumber, ?int $policyId, array $required, array $placed): array
    {
        $materialitySi = (float) config('fac.coverage.materiality_si', 1000.0);

        $requiredSi  = (float) ($required['sumInsured'] ?? 0);
        $mandatorySi = (float) ($required['mandatorySumInsured'] ?? 0);
        $placedSi    = (float) ($placed['sumInsured'] ?? 0);

        $requiresFac = $requiredSi > $materialitySi;

        // `gap` stays in PULA of ceded premium because the register's existing
        // screens label that column BWP, and a sum insured shown under a Pula
        // heading would mislead worse than the figure it replaced. The exposure
        // gap — the one that answers "is the whole risk covered" — is reported
        // alongside as gapSumInsured, for whatever reads it next.
        $gap            = round((float) ($required['premium'] ?? 0) - (float) ($placed['premiumExclVat'] ?? 0), 2);
        $gapSumInsured  = round($requiredSi - $placedSi, 2);

        $verdict = match (true) {
            !$policyId                                  => 'policy_not_in_graphite',
            !$requiresFac && $placed['liveCount'] === 0 => 'no_fac_required',
            !$requiresFac && $placed['liveCount'] > 0   => 'placed_without_requirement',
            $placed['liveCount'] === 0                  => 'fac_required_none_placed',
            // Judged on exposure, not premium: a risk can be under-placed in sum
            // insured while the premium happens to line up.
            $gapSumInsured > $materialitySi             => 'fac_under_placed',
            $gapSumInsured < -$materialitySi            => 'fac_over_placed',
            default                                     => 'covered',
        };

        // A foreign line with no rate only blocks the PREMIUM view. The sum
        // insured is captured in its own right, so the exposure position is
        // still assessable — the old behaviour hid the whole policy.
        $premiumAssessable = ((int) ($placed['rateMissing'] ?? 0)) === 0;

        // Per-class comparison: shortfall against what is placed on that class.
        $placedByGroup = $placed['byGroup'] ?? collect();
        $breakdown = [];
        foreach ($required['byGroup'] ?? [] as $g) {
            $p = $placedByGroup[$g['groupId']] ?? null;
            $pl = $p ? (float) $p->placed_si : 0.0;
            $breakdown[] = [
                'groupId'    => $g['groupId'],
                'groupCode'  => $g['groupCode'],
                'riskSi'     => $g['riskSi'],
                'treatySi'   => $g['treatySi'],
                'outsideSi'  => $g['outsideSi'],
                'placedSi'   => round($pl, 2),
                'unplacedSi' => round($g['outsideSi'] - $pl, 2),
                'mandatory'  => (bool) $g['mandatory'],
                'lines'      => $p ? (int) $p->line_count : 0,
            ];
        }

        return [
            'policyId'            => $policyId,
            'policyNumber'        => $policyNumber,
            'requiresFac'         => $requiresFac,
            'requiredPremium'     => (float) ($required['premium'] ?? 0),
            'requiredSumInsured'  => $requiredSi,
            'mandatorySumInsured' => $mandatorySi,
            'placedPremium'       => (float) ($placed['premiumExclVat'] ?? 0),
            'placedSumInsured'    => $placedSi,
            'unallocatedSumInsured' => (float) ($placed['unallocatedSi'] ?? 0),
            'placedCount'         => (int) $placed['liveCount'],
            'gap'                 => $gap,
            'gapSumInsured'       => $gapSumInsured,
            'premiumAssessable'   => $premiumAssessable,
            'verdict'             => $verdict,
            'verdictLabel'        => self::VERDICT_LABELS[$verdict] ?? $verdict,
            'breakdown'           => $breakdown,
            'detail'              => ['required' => $required, 'placed' => $placed],
        ];
    }

    public const VERDICT_LABELS = [
        'policy_not_in_graphite'     => 'Policy is not in Graphite — cannot be checked',
        'no_fac_required'            => 'Fully absorbed by the treaty',
        'placed_without_requirement' => 'A placement exists but the treaty absorbed the whole risk',
        'fac_required_none_placed'   => 'Outside the treaty — nothing placed',
        'fac_under_placed'           => 'Under-placed — less placed than sits outside the treaty',
        'fac_over_placed'            => 'Over-placed — more placed than sits outside the treaty',
        'covered'                    => 'Fully accounted for',
    ];

    /** Verdicts that mean somebody has to act. */
    public const EXCEPTION_VERDICTS = [
        'fac_required_none_placed',
        'fac_under_placed',
        'fac_over_placed',
        'placed_without_requirement',
    ];

    /**
     * Sweep every policy carrying sum insured its treaty did not absorb, and
     * report what the register cannot account for.
     *
     * Driven FROM the cession figures, not from the register: a policy missing
     * from the register entirely is exactly the case worth finding, and a
     * register-driven scan would never see it.
     *
     * @param  bool $exceptionsOnly  drop the clean ones
     * @return array<int,array>
     */
    public function scan(bool $exceptionsOnly = true, int $limit = 5000): array
    {
        $materialitySi  = (float) config('fac.coverage.materiality_si', 1000.0);
        $mandatoryAbove = (float) config('fac.coverage.mandatory_si_threshold', 50000000.0);

        // THE WHOLE BOOK IN ONE STATEMENT, still. The seam hands back a QUERY
        // rather than rows precisely so this stays a single roll-up -- fetching
        // per policy would turn one statement into one per policy, and this
        // scans up to 5,000 of them.
        //
        // The latest-action join lives inside the seam because the table and
        // alias differ by basis, so a caller cannot express it without knowing
        // which basis is live -- which is the one thing the seam exists to hide.
        $units = app(CessionSource::class)->riskUnitsQuery(null, null, true);

        $rows = DB::query()
            ->fromSub($units, 'u')
            ->leftJoin('policies as p', 'p.id', '=', 'u.policy_id')
            ->groupBy('u.policy_id', 'p.policyNumber', 'p.status')
            ->havingRaw('SUM(GREATEST(u.risk_si - u.treaty_si, 0)) > ?', [$materialitySi])
            ->select(
                'u.policy_id',
                'p.policyNumber',
                'p.status',
                DB::raw('SUM(GREATEST(u.risk_si - u.treaty_si, 0))          as required_si'),
                DB::raw('SUM(GREATEST(u.risk_premium - u.treaty_premium,0)) as required_premium'),
                DB::raw("SUM(CASE WHEN u.risk_si > {$mandatoryAbove}"
                    . " THEN GREATEST(u.risk_si - u.treaty_si, 0) ELSE 0 END) as mandatory_si")
            )
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // One query for the placed side, keyed by policy number.
        $numbers = $rows->pluck('policyNumber')->filter()->values()->all();
        $placedByNumber = DB::table('fac_placements')
            ->whereIn('policy_number', $numbers)
            ->whereNull('deleted_at')
            ->groupBy('policy_number')
            ->select(
                'policy_number',
                DB::raw("SUM(CASE WHEN currency = 'BWP' THEN COALESCE(gross_ceded_premium_excl_vat,0)"
                    . " ELSE COALESCE(gross_ceded_premium_bwp,0) END) as premium_excl_vat"),
                DB::raw("SUM(CASE WHEN status <> 'cancelled' THEN COALESCE(cession_sum_insured,0) ELSE 0 END) as sum_insured"),
                DB::raw("SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END) as live_count"),
                DB::raw('COUNT(*) as total_count'),
                DB::raw("SUM(CASE WHEN currency <> 'BWP' AND fx_rate IS NULL THEN 1 ELSE 0 END) as rate_missing"),
                DB::raw("SUM(CASE WHEN status <> 'cancelled' AND reinsurance_group_id IS NULL"
                    . " THEN COALESCE(cession_sum_insured,0) ELSE 0 END) as unallocated_si")
            )
            ->get()
            ->keyBy('policy_number');

        $out = [];
        foreach ($rows as $r) {
            $pl = $placedByNumber->get($r->policyNumber);
            $placed = [
                'premiumExclVat' => round((float) ($pl->premium_excl_vat ?? 0), 2),
                'sumInsured'     => round((float) ($pl->sum_insured ?? 0), 2),
                'unallocatedSi'  => round((float) ($pl->unallocated_si ?? 0), 2),
                'liveCount'      => (int) ($pl->live_count ?? 0),
                'totalCount'     => (int) ($pl->total_count ?? 0),
                'rateMissing'    => (int) ($pl->rate_missing ?? 0),
                'byGroup'        => collect(),
            ];
            $required = [
                'premium'             => round((float) $r->required_premium, 2),
                'sumInsured'          => round((float) $r->required_si, 2),
                'mandatorySumInsured' => round((float) $r->mandatory_si, 2),
                'rows'                => 0,
                'actionId'            => null,
                'byGroup'             => [],
            ];

            $result = $this->assemble((string) $r->policyNumber, (int) $r->policy_id, $required, $placed);
            $result['policyStatus'] = (int) $r->status;

            if ($exceptionsOnly && !in_array($result['verdict'], self::EXCEPTION_VERDICTS, true)) {
                continue;
            }
            $out[] = $result;
        }

        // Worst gap first — that is the order somebody should work it in.
        usort($out, fn ($a, $b) => $b['gap'] <=> $a['gap']);

        return $out;
    }

    /** Headline counts for the register's tiles. */
    public function scanSummary(): array
    {
        $all = $this->scan(false);

        $count = static fn (array $rows, string $v) => count(array_filter($rows, fn ($r) => $r['verdict'] === $v));

        return [
            'policiesRequiringFac' => count($all),
            'covered'              => $count($all, 'covered'),
            'nonePlaced'           => $count($all, 'fac_required_none_placed'),
            'underPlaced'          => $count($all, 'fac_under_placed'),
            'overPlaced'           => $count($all, 'fac_over_placed'),
            // Ceded PREMIUM still unplaced. Kept in Pula because the register's
            // tile reads "Ceded premium unplaced".
            'exposureUnplacedBwp'  => round(array_sum(array_map(
                fn ($r) => max($r['gap'], 0),
                $all
            )), 2),
            // SUM INSURED still unplaced, and the portion of it BR-STD-01 makes
            // mandatory to place.
            'exposureUnplacedSi'   => round(array_sum(array_map(
                fn ($r) => max($r['gapSumInsured'], 0),
                $all
            )), 2),
            'mandatoryUnplacedSi'  => round(array_sum(array_map(
                fn ($r) => $r['gapSumInsured'] > 0 ? min($r['gapSumInsured'], $r['mandatorySumInsured']) : 0,
                $all
            )), 2),
        ];
    }
}
