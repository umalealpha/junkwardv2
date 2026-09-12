<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Facades\DB;

/**
 * The wire between the policy and RegulatoryCessionCalculator.
 *
 * The calculator is a pure function — sum insured and a regulatory mapping in,
 * an allocation out. It had no callers: everything live still routes through the
 * formula chain in PolicyCoverage::getReinsuranceCoverageCalculations(), which
 * reads the reinsurance GROUP. This class is what lets the mapping decide instead.
 *
 * THE DIFFERENCE IS THE ROUTE, NOT THE ARITHMETIC.
 *
 *   Legacy      coverage -> reinsurance group -> formulas attached to the treaty
 *               -> layers. A group with no formula cedes nothing, silently.
 *   Regulatory  coverage -> reinsurance group -> regulatory_mapping -> the
 *               treatment that mapping carries. A group with no mapping is
 *               reported as retained WITH a reason, not skipped.
 *
 * Reinsurance's instruction of 17 August 2026: "We use regulatory mapping for
 * treaties instead of the coverages."
 *
 * THE GRAIN. Sum insured is aggregated per (risk address, mapping, group) before
 * it reaches the calculator, because that is the unit the treaty tests:
 *
 *   - Property and Engineering are LAYERED per risk address, so two Property
 *     groups at one address must share one 3m / 7m / 40m stack. The calculator
 *     does that rollup itself, which is why risk_address travels with the risk.
 *   - Motor, Transportation, Miscellaneous and Guarantee are CAPPED per risk —
 *     "any one motor vehicle", "any one trailer" — so they stay separate.
 *
 * NOTHING HERE WRITES. Reading is deliberate: the live tab still publishes the
 * legacy figures, and this exists first to be compared against them. See
 * config/reinsurance.php 'engine'.
 */
class RegulatoryCessionService
{
    /** Build the calculator from configuration rather than its own defaults. */
    public function calculator(): RegulatoryCessionCalculator
    {
        return new RegulatoryCessionCalculator(
            (array) config('reinsurance.params', []),
            (array) config('reinsurance.class_limits', []),
            (array) config('reinsurance.group_limits', []),
            (array) config('reinsurance.retained_groups', [])
        );
    }

    /**
     * The risks on a policy action, on the regulatory mapping basis.
     *
     * READ FROM policy_reinsurance_details, WHICH IS WHAT THE TREATY ACTUALLY
     * CEDES FROM. This was rebuilt on 31 August after the earlier read — over
     * policy_coverage_detail — could not be reconciled to Reinsurance's own
     * workbook for COMG2026213751.
     *
     * Two things make the staged table the right source and the coverage rows the
     * wrong one:
     *
     *   MOTOR NEVER ARRIVES AS A COVERAGE VALUE. The legacy motor path reads
     *   prid.n_SumInsured and nothing else — motor sum insured comes through the
     *   vehicle, so reading coverage_value lost the whole class. On the test
     *   policy that was 38,000,000 missing, and no amount of joining fixed it.
     *
     *   THE STAGED TABLE HAS ALREADY RESOLVED THE GROUP. A coverage code sits in
     *   several reinsurance groups, so reading from coverage rows meant guessing
     *   which group owned each one and scoping by product to narrow it. Here the
     *   group is a column, decided upstream by the same engine the comparison is
     *   against.
     *
     * ONE ROW PER LAYER, SO COUNT THE SUM INSURED ONCE. The table carries a row
     * per formula on the group, repeating the same sum insured on each. MOTOR_COM
     * on the test policy holds 8 rows over 2 coverage details, summing to
     * 152,000,000 against a real 38,000,000 — four layers. The inner query
     * collapses to one figure per (coverage detail, risk address) before anything
     * is summed.
     *
     * On COMG2026213751 this reproduces Reinsurance's workbook group for group,
     * and totals 687,704,212 against their 687,704,212.
     *
     * IT DEPENDS ON COMPUTE HAVING RUN. An action that has never been computed
     * has no staged rows and reads as empty — correctly, since the legacy engine
     * has nothing to cede from either. The report says so rather than showing a
     * confident zero.
     *
     * @return array<int,array{sum_insured:float,premium:float,mapping:string,group:string,risk_address:int|string|null}>
     */
    public function risksFor(int $actionId): array
    {
        $rows = DB::select($this->risksSql(), [$actionId]);

        return array_map(fn ($r) => [
            'risk_address' => $r->risk_address,
            'group'        => (string) $r->group_code,
            'mapping'      => (string) $r->mapping,
            'sum_insured'  => (float) $r->sum_insured,
            'premium'      => (float) $r->premium,
        ], $rows);
    }

    /**
     * Groups whose coverage rows disagree on the regulatory class.
     *
     * Reported, never resolved. The class is a property of the group, so a group
     * holding two different classes cannot be routed without choosing one, and
     * choosing would file a sum insured under a class nobody picked.
     *
     * @return array<int,array{group:string,classes:string,rows:int}>
     */
    public function ambiguousCoverages(int $actionId): array
    {
        $rows = DB::select("
            SELECT g.group_code                        AS group_code,
                   COUNT(DISTINCT gc.id)               AS rows_,
                   GROUP_CONCAT(DISTINCT gc.regulatory_mapping) AS classes
            FROM policy_reinsurance_details prid
            JOIN reinsurance_group g            ON g.id = prid.group_id
            JOIN reinsurance_group_coverage gc  ON gc.group_id = g.id
            WHERE prid.action_id = ?
            GROUP BY g.group_code
            HAVING COUNT(DISTINCT gc.regulatory_mapping) > 1
            ORDER BY rows_ DESC
        ", [$actionId]);

        return array_map(function ($r) {
            // Sorted here rather than in SQL: MySQL's ORDER BY inside
            // GROUP_CONCAT is not portable and this runs under sqlite in test.
            $classes = array_values(array_filter(array_map('trim', explode(',', (string) $r->classes))));
            sort($classes);

            return [
                'group'   => (string) $r->group_code,
                'classes' => implode(', ', $classes),
                'rows'    => (int) $r->rows_,
            ];
        }, $rows);
    }

    /**
     * The read, as one statement.
     *
     * Kept apart from risksFor() so the collapse is legible: the subquery reduces
     * the staged rows to ONE figure per coverage detail per risk address before
     * the outer query sums anything.
     */
    private function risksSql(): string
    {
        return "
            SELECT
                d.risk_address                                        AS risk_address,
                g.group_code                                          AS group_code,
                -- THE COVERAGE ROWS FIRST, THE GROUP ROW AS A FALLBACK.
                -- reinsurance_group carries a regulatory_mapping of its own and
                -- five groups have one with no coverage rows behind it --
                -- AVIATION_COM (Transportation), BODY_CORPORATE_COM (Liability),
                -- MOTOR_PER_ACCIDENT (Motor) and both TRAVELINSURANCE groups.
                -- Reading only the coverage rows sees nothing for those, and a
                -- group with no mapping is treated as outside the treaty and
                -- retained whole. None of them carries business today, so this
                -- fixes a trap rather than a loss: the day Aviation is written
                -- it would have been retained in full and nobody would have seen
                -- an error, because 100% retained is a legitimate answer.
                COALESCE(
                    NULLIF(TRIM(gmap.regulatory_mapping), ''),
                    NULLIF(TRIM(g.regulatory_mapping), ''),
                    ''
                ) AS mapping,
                SUM(d.si)                                             AS sum_insured,
                SUM(d.prem)                                           AS premium
            FROM (
                SELECT prid.group_id                        AS group_id,
                       prid.risk_address_id                 AS risk_address,
                       prid.pocoverage_detail_id            AS detail_id,
                       MAX(COALESCE(prid.n_SumInsured, 0))  AS si,
                       MAX(COALESCE(prid.n_Premium, 0))     AS prem
                FROM policy_reinsurance_details prid
                WHERE prid.action_id = ?
                GROUP BY prid.group_id, prid.risk_address_id, prid.pocoverage_detail_id
            ) d
            JOIN reinsurance_group g ON g.id = d.group_id
            LEFT JOIN (
                SELECT group_id, MIN(regulatory_mapping) AS regulatory_mapping
                FROM reinsurance_group_coverage
                GROUP BY group_id
            ) gmap ON gmap.group_id = d.group_id
            GROUP BY d.risk_address, g.group_code, gmap.regulatory_mapping, g.regulatory_mapping
            ORDER BY d.risk_address, g.group_code
        ";
    }

    /**
     * Allocate a policy action on the regulatory basis.
     *
     * @return array{units:array<int,array<string,mixed>>, totals:array<string,float>, exceptions:array<int,array<string,mixed>>}
     */
    public function allocate(int $actionId): array
    {
        $risks  = $this->risksFor($actionId);
        $result = $this->calculator()->allocatePolicy($risks);

        // The calculator totals its own output, so the figures here and the ones
        // its tests pin are the same figures. Re-summing them separately would be
        // a second implementation of the same arithmetic, free to drift.
        $units = $result['per_risk'];

        return [
            'units'      => $units,
            'by_mapping' => $result['by_mapping'],
            'totals'     => $result['totals'] ?: [],
            'exceptions' => array_values(array_filter(
                $units,
                fn ($u) => !empty($u['exception'])
            )),
        ];
    }

    /**
     * The regulatory allocation expressed as policy_reinsurance rows.
     *
     * THIS IS THE MISSING PIECE BEFORE ANY CUTOVER, and it is why the cutover is
     * a build rather than a flag flip. config/reinsurance.php carries an 'engine'
     * setting, but nothing reads it: the whole application takes its cession from
     * policy_reinsurance, which the legacy engine writes. Switching engines means
     * the regulatory basis has to produce rows of that shape, and the two bases do
     * not have the same shape.
     *
     * THE SHAPES DISAGREE, AND THAT IS THE REAL PROBLEM. policy_reinsurance holds
     * one row per (group, formula, coverage): a coverage running four layers is
     * four rows, each carrying the same totalSumInsured. The regulatory basis
     * holds one allocation per (risk address, mapping) split across named LAYERS —
     * net retention, quota share, surplus, Auto FAC, FAC, outside treaty. There is
     * no formula behind a regulatory layer, so formula_id, type_id and UsedFormula
     * cannot be filled honestly and are left null rather than invented.
     *
     * WHAT THIS IS FOR. Rows produced here are for COMPARISON, not for writing.
     * Reinsurance can put them beside what the legacy engine stored and agree the
     * movement line by line before anything is switched. Nothing here writes.
     *
     * @return array<int,array<string,mixed>>
     */
    public function asPolicyReinsuranceRows(int $actionId): array
    {
        $allocation = $this->allocate($actionId);
        $rows       = [];

        // group_code back to group_id, so a row can be compared against the
        // legacy row for the same group.
        $groupIds = DB::table('reinsurance_group')->pluck('id', 'group_code');

        foreach ($allocation['units'] as $u) {
            // A layered unit can span several groups at one risk address, so the
            // group is only meaningful where exactly one produced it.
            $groups   = $u['groups'] ?? array_filter([$u['group'] ?? null]);
            $groupKey = count($groups) === 1 ? reset($groups) : null;

            $si = (float) $u['sum_insured'];

            $rows[] = [
                'action_id'        => $actionId,
                'group_id'         => $groupKey !== null ? ($groupIds[$groupKey] ?? null) : null,
                'group_code'       => $groupKey,
                'groups'           => array_values($groups),
                'risk_address_id'  => $u['risk_address'] ?? null,
                'regulatory_class' => $u['mapping'],
                'totalSumInsured'  => $si,
                'totalPremium'     => (float) $u['premium'],
                'treatySI'         => (float) $u['ceded_si'],
                'treatyPremium'    => (float) $u['ceded_premium'],
                'treatyPercentage' => $si > 0.0 ? round($u['ceded_si'] / $si * 100, 4) : 0.0,

                // The layers, which policy_reinsurance has nowhere to put. Carried
                // so the comparison can explain a difference rather than only
                // measure it.
                'layers' => [
                    'net_retention' => (float) $u['net_retention_si'],
                    'quota_share'   => (float) $u['quota_share_si'],
                    'surplus'       => (float) $u['surplus_si'],
                    'auto_fac'      => (float) $u['auto_fac_si'],
                    'fac'           => (float) $u['fac_si'],
                    'outside'       => (float) $u['outside_treaty_si'],
                ],

                // Cannot be filled honestly from a regulatory allocation.
                'formula_id'  => null,
                'type_id'     => null,
                'UsedFormula' => null,
            ];
        }

        return $rows;
    }

    /**
     * Which engine config says should decide live cession.
     *
     * Read this rather than the config key directly, so the one place that has to
     * change when the cutover happens is here. It currently has no effect on any
     * stored figure — see asPolicyReinsuranceRows() for what a cutover needs.
     */
    /**
     * The six layers a regulatory allocation splits into, and what each one IS.
     *
     * The distinction that matters is not the amount, it is whether anybody is
     * carrying the risk:
     *
     *   · quota_share and surplus are TREATY CAPACITY. Placed, by definition.
     *   · auto_fac and fac are capacity that EXISTS BUT MUST BE PLACED PER RISK.
     *     Reinsurance's own work paper, open item 8: "That percentage is only
     *     reinsured if a slip has actually been placed on that risk. If it has
     *     not, the percentage is an uninsured net exposure." So is_placed is
     *     NULL until a facultative slip says otherwise — not false, which would
     *     assert it had been checked, and not true, which would assert cover.
     *   · net_retention and outside_treaty are ours. Outside-treaty is the worse
     *     of the two: retention is a decision, outside-treaty is exposure with no
     *     capacity behind it at all.
     */
    public const LAYERS = [
        'net_retention'  => ['field' => 'net_retention_si',  'treaty' => false, 'placed' => true],
        'quota_share'    => ['field' => 'quota_share_si',    'treaty' => true,  'placed' => true],
        'surplus'        => ['field' => 'surplus_si',        'treaty' => true,  'placed' => true],
        'auto_fac'       => ['field' => 'auto_fac_si',       'treaty' => true,  'placed' => null],
        'fac'            => ['field' => 'fac_si',            'treaty' => true,  'placed' => null],
        'outside_treaty' => ['field' => 'outside_treaty_si', 'treaty' => false, 'placed' => false],
    ];

    /**
     * Store the regulatory allocation for an action.
     *
     * WRITES ONLY TO policy_reinsurance_regulatory. It does not touch
     * policy_reinsurance and it does not decide what the application reports —
     * that is still the legacy chain, and it stays that way until the routing is
     * signed off. Storing both bases side by side is what makes the cutover
     * reversible, and what lets the movement be agreed on real stored rows
     * rather than on a recomputation that could itself drift.
     *
     * IDEMPOTENT BY ACTION. Re-running replaces that action's rows inside one
     * transaction rather than appending a second opinion, so a half-finished run
     * cannot leave an action holding two allocations.
     *
     * A NIL LAYER IS NOT WRITTEN. Six rows per risk address where four are zero
     * is noise that would treble the table for no information; the layers that
     * carry nothing are recoverable by their absence.
     *
     * @return array{action_id:int,rows:int,units:int,exceptions:int,sum_insured:float,ceded:float}
     */
    public function persist(int $actionId, ?string $by = null): array
    {
        $allocation = $this->allocate($actionId);
        $groupIds   = DB::table('reinsurance_group')->pluck('id', 'group_code');

        $meta = DB::table('policy_reinsurance')
            ->where('action_id', $actionId)
            ->select('policy_id', 'term_id', 'transaction_id', 'treaty_id')
            ->first();

        $now  = now();
        $rows = [];

        foreach ($allocation['units'] as $u) {
            $groups   = $u['groups'] ?? array_filter([$u['group'] ?? null]);
            $groupKey = count($groups) === 1 ? reset($groups) : null;
            $si       = (float) ($u['sum_insured'] ?? 0);
            $rate     = $si > 0.0 ? ((float) ($u['premium'] ?? 0)) / $si : 0.0;

            foreach (self::LAYERS as $layer => $spec) {
                $amount = (float) ($u[$spec['field']] ?? 0);

                if (abs($amount) < 0.005) {
                    continue;
                }

                $rows[] = [
                    'action_id'          => $actionId,
                    'policy_id'          => $meta->policy_id ?? null,
                    'term_id'            => $meta->term_id ?? null,
                    'transaction_id'     => $meta->transaction_id ?? null,
                    'risk_address'       => $u['risk_address'] ?? null,
                    'regulatory_class'   => (string) $u['mapping'],
                    'layer'              => $layer,
                    'group_id'           => $groupKey !== null ? ($groupIds[$groupKey] ?? null) : null,
                    'group_code'         => $groupKey,
                    'treaty_id'          => $meta->treaty_id ?? null,
                    'sum_insured'        => round($amount, 2),
                    // Premium follows the sum insured at the policy's own rate.
                    // On a nil sum insured there is no rate to apportion on, and
                    // the exception carried on the unit says why.
                    'premium'            => round($amount * $rate, 2),
                    'is_treaty_capacity' => $spec['treaty'],
                    'is_placed'          => $spec['placed'],
                    'route'              => $u['treaty_route'] ?? null,
                    'exception'          => $u['exception'] ?? null,
                    'basis_version'      => (string) config('reinsurance.basis_version', '2026-27'),
                    'added_by'           => $by,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        }

        DB::transaction(function () use ($actionId, $rows) {
            DB::table('policy_reinsurance_regulatory')->where('action_id', $actionId)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('policy_reinsurance_regulatory')->insert($chunk);
            }
        });

        return [
            'action_id'   => $actionId,
            'rows'        => count($rows),
            'units'       => count($allocation['units']),
            'exceptions'  => count($allocation['exceptions']),
            'sum_insured' => round((float) ($allocation['totals']['sum_insured'] ?? 0), 2),
            'ceded'       => round((float) ($allocation['totals']['ceded_si'] ?? 0), 2),
        ];
    }

    /**
     * What was stored for an action on the regulatory basis, by layer.
     *
     * Read back from the table rather than recomputed, so a caller checking the
     * cutover is looking at what is actually persisted.
     *
     * @return array<string,array{sum_insured:float,premium:float,rows:int}>
     */
    public function storedLayers(int $actionId): array
    {
        $rows = DB::table('policy_reinsurance_regulatory')
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->groupBy('layer')
            ->selectRaw('layer, SUM(sum_insured) AS si, SUM(premium) AS prem, COUNT(*) AS n')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->layer] = [
                'sum_insured' => round((float) $r->si, 2),
                'premium'     => round((float) $r->prem, 2),
                'rows'        => (int) $r->n,
            ];
        }

        return $out;
    }

    public function engine(): string
    {
        $engine = strtolower((string) config('reinsurance.engine', 'legacy'));

        return in_array($engine, ['legacy', 'regulatory'], true) ? $engine : 'legacy';
    }

    /** True while the legacy formula chain still decides what is stored. */
    public function legacyEngineIsLive(): bool
    {
        return $this->engine() === 'legacy';
    }

    /**
     * What the legacy chain STORED for an action. NOT a cession figure.
     *
     * IT WAS CALLED ceded_si AND IT WAS NOT ONE. treatySI is populated on every
     * layer row the legacy chain writes -- net retention, quota share, surplus,
     * facultative and facultative placement alike -- so summing it adds the
     * retained leg to the ceded one. Book-wide that came to 5.64bn against a
     * book of 3.93bn, and more than half of it was facultative placement rows.
     * Every comparison built on it was regulatory CESSION against legacy
     * TOTAL-ALLOCATED, which is not a comparison.
     *
     * AND IT CANNOT SIMPLY BE NARROWED TO THE CEDED TYPES. A group runs several
     * formulas and EACH emits its own full set of layer rows: on
     * PROPERTYANDBI_COM, formula 2 (quota share) and formula 68 (net retention)
     * both write rows carrying type_id 1, 3, 4, 5 and 6. So type_id identifies
     * the layer within one formula's output, not the formula's own role, and
     * filtering on it double counts across formulas instead of across layers.
     *
     * Deriving a defensible legacy cession needs the formula semantics, which
     * are not documented and which I have now misread three ways. So this
     * returns what it can defend -- the total allocated, and the shape of what
     * produced it -- and says plainly that it is not a cession. The counts are
     * returned because a row set spanning several formulas is the thing that
     * makes the total unusable, and a caller should be able to see that.
     *
     * NOTHING SHOULD COMPARE THIS TO THE REGULATORY CESSION. The case for the
     * regulatory basis does not need it: that basis reproduces Reinsurance's own
     * COMG2026213751 working to the cent, and fourteen of their eighteen test
     * cases. A delta against a figure nobody can define adds nothing to it.
     *
     * @return array<string,float|int|bool>
     */
    public function legacyTotals(int $actionId): array
    {
        $row = DB::selectOne("
            SELECT
                SUM(COALESCE(REPLACE(treatySI, ',', ''), 0))      AS allocated_si,
                SUM(COALESCE(REPLACE(treatyPremium, ',', ''), 0)) AS allocated_premium,
                COUNT(*)                                          AS rows_seen,
                COUNT(DISTINCT formula_id)                        AS formulas,
                COUNT(DISTINCT type_id)                           AS layer_types
            FROM policy_reinsurance
            WHERE action_id = ?
              AND deleted_at IS NULL
        ", [$actionId]);

        return [
            'allocated_si'      => (float) ($row->allocated_si ?? 0),
            'allocated_premium' => (float) ($row->allocated_premium ?? 0),
            'rows'              => (int) ($row->rows_seen ?? 0),
            'formulas'          => (int) ($row->formulas ?? 0),
            'layer_types'       => (int) ($row->layer_types ?? 0),
            'is_cession'        => false,
        ];
    }

    /**
     * Legacy against regulatory, for one action.
     *
     * This is the reconciliation Reinsurance has to agree before the engine is
     * switched over. A difference is not necessarily an error — routing by mapping
     * instead of by group is the whole point, and it is expected to move some
     * figures — but every difference has to be explainable before live cession
     * changes.
     */
    public function compare(int $actionId): array
    {
        $regulatory = $this->allocate($actionId);
        $legacy     = $this->legacyTotals($actionId);

        $cededSi      = (float) ($regulatory['totals']['ceded_si'] ?? 0);
        $cededPremium = (float) ($regulatory['totals']['ceded_premium'] ?? 0);

        return [
            'action_id'  => $actionId,
            'legacy'     => $legacy,
            'regulatory' => [
                'ceded_si'      => $cededSi,
                'ceded_premium' => $cededPremium,
                'retained_si'   => (float) ($regulatory['totals']['retained_si'] ?? 0),
                'sum_insured'   => (float) ($regulatory['totals']['sum_insured'] ?? 0),
                'rows'          => count($regulatory['units']),
            ],
            // NO DELTA. legacyTotals() returns what the legacy chain ALLOCATED
            // across every layer and every formula, not what it ceded, so a
            // difference against the regulatory cession measures nothing. Both
            // sides are reported; subtracting them is left undone deliberately.
            'delta'      => null,
            // Nothing was published for this action, so there is nothing to compare
            // against. Said out loud, because a zero delta would otherwise read as
            // agreement between the two engines.
            'legacy_published' => $legacy['rows'] > 0,
            'units'      => $regulatory['units'],
            'by_mapping' => $regulatory['by_mapping'],
            'exceptions' => $regulatory['exceptions'],
        ];
    }
}
