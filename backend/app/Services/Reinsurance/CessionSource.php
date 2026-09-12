<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The one place that decides which basis the application READS.
 *
 * Before this, nineteen files reached into policy_reinsurance directly, so a
 * cutover meant editing nineteen files and hoping. Now the basis is decided
 * once, here, and a call site that has been migrated follows the flag without
 * knowing there is one.
 *
 * WHAT THIS IS NOT. It is not a write seam and must never become one. Of those
 * nineteen files most are on the COMPUTE path -- PolicyCoverage alone makes
 * thirty-four write calls, ClaimsV2Controller twenty-eight -- and that is the
 * legacy chain producing the cession in the first place. The regulatory basis
 * does not write to policy_reinsurance and should not: the two shapes disagree,
 * which is the whole reason policy_reinsurance_regulatory exists. Routing writes
 * through here would put the regulatory allocation into a table that cannot hold
 * it honestly.
 *
 * ON THE LEGACY BASIS IT IS A PASS-THROUGH. Reading through this returns exactly
 * what reading policy_reinsurance directly returns today, so migrating a call
 * site changes nothing until the flag moves. That is what makes the migration
 * safe to do before the sign-off rather than after it.
 *
 * A MISSING ALLOCATION IS NOT A ZERO. If the flag is flipped before every action
 * has been persisted, an unbackfilled action has no regulatory rows -- and
 * reporting that as nil cession would understate the reinsurance asset on every
 * policy nobody remembered to run. It raises instead. A loud failure at cutover
 * is recoverable; a quiet zero on a solvency return is not.
 */
class CessionSource
{
    public const BASIS_LEGACY     = 'legacy';
    public const BASIS_REGULATORY = 'regulatory';

    /**
     * A regulatory layer expressed as the legacy type_code and type_name.
     *
     * THE CODE IS LOAD-BEARING, NOT COSMETIC. ClaimsV2Controller decides whether
     * a line is retained or ceded with type_code === 'NETRETENTION', so the
     * regulatory branch has to emit that exact string or every net-retention
     * line would report as ceded the moment the flag moved -- overstating the
     * reinsurance recovery on every claim.
     *
     * AUTO FAC AND FAC COLLAPSE INTO ONE BUCKET, as they already do in
     * layerRowsForPolicy and on the reinsurance tab. Keeping them apart here
     * would split one legacy row into two on the regulatory basis and the two
     * bases would stop being comparable line for line.
     */
    private const LAYER_CODE_SQL = "CASE r.layer
        WHEN 'net_retention'  THEN 'NETRETENTION'
        WHEN 'quota_share'    THEN 'QUOTASHARING'
        WHEN 'surplus'        THEN 'SURPLUS'
        WHEN 'auto_fac'       THEN 'FACULTATIVE'
        WHEN 'fac'            THEN 'FACULTATIVE'
        WHEN 'outside_treaty' THEN 'OUTSIDE_TREATY'
        ELSE UPPER(r.layer) END";

    private const LAYER_NAME_SQL = "CASE r.layer
        WHEN 'net_retention'  THEN 'Net Retention'
        WHEN 'quota_share'    THEN 'Quota Share'
        WHEN 'surplus'        THEN 'Surplus'
        WHEN 'auto_fac'       THEN 'Facultative'
        WHEN 'fac'            THEN 'Facultative'
        WHEN 'outside_treaty' THEN 'Outside Treaty'
        ELSE r.layer END";

    public function __construct(private RegulatoryCessionService $regulatory)
    {
    }

    /** Which basis is live. Decided here and nowhere else. */
    public function basis(): string
    {
        return $this->regulatory->engine() === self::BASIS_REGULATORY
            ? self::BASIS_REGULATORY
            : self::BASIS_LEGACY;
    }

    public function isRegulatory(): bool
    {
        return $this->basis() === self::BASIS_REGULATORY;
    }

    /**
     * Ceded sum insured and premium for an action, on whichever basis is live.
     *
     * @return array{basis:string,ceded_si:float,ceded_premium:float,rows:int}
     */
    public function totalsFor(int $actionId): array
    {
        if (! $this->isRegulatory()) {
            // ON LEGACY THIS IS THE TOTAL ALLOCATED, NOT THE CESSION, and it is
            // returned under is_cession=false so no caller can mistake it. The
            // legacy chain writes treatySI on every layer row and each formula
            // emits its own full set, so the sum carries the retained leg too.
            // A defensible legacy cession needs the formula semantics; until
            // they are known this reports what it can stand behind.
            $t = $this->regulatory->legacyTotals($actionId);

            return [
                'basis'         => self::BASIS_LEGACY,
                'ceded_si'      => (float) ($t['allocated_si'] ?? 0),
                'ceded_premium' => (float) ($t['allocated_premium'] ?? 0),
                'rows'          => (int) ($t['rows'] ?? 0),
                'is_cession'    => false,
            ];
        }

        $row = DB::table('policy_reinsurance_regulatory')
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            // Ceded is the treaty legs only. Auto FAC and FAC are capacity that
            // must be placed per risk and outside-treaty is nobody's, so neither
            // is a cession -- the same rule the calculator applies.
            ->whereIn('layer', ['quota_share', 'surplus'])
            ->selectRaw('COALESCE(SUM(sum_insured),0) AS si, COALESCE(SUM(premium),0) AS prem, COUNT(*) AS n')
            ->first();

        $this->guardPersisted($actionId);

        return [
            'basis'         => self::BASIS_REGULATORY,
            'ceded_si'      => round((float) $row->si, 2),
            'ceded_premium' => round((float) $row->prem, 2),
            'rows'          => (int) $row->n,
            'is_cession'    => true,
        ];
    }

    /**
     * The cession broken down, on whichever basis is live.
     *
     * The KEY DIFFERS BY BASIS and that is not a defect to paper over: legacy
     * groups by reinsurance group because that is the grain it stores, and the
     * regulatory basis groups by regulatory class because that is the grain the
     * treaty cedes at. A caller that needs one specific grain should ask for it
     * rather than take whichever this returns.
     *
     * @return array<string,array{ceded_si:float,ceded_premium:float}>
     */
    public function byKeyFor(int $actionId): array
    {
        if ($this->isRegulatory()) {
            $this->guardPersisted($actionId);

            $rows = DB::table('policy_reinsurance_regulatory')
                ->where('action_id', $actionId)
                ->whereNull('deleted_at')
                ->whereIn('layer', ['quota_share', 'surplus'])
                ->groupBy('regulatory_class')
                ->selectRaw('regulatory_class AS k, SUM(sum_insured) AS si, SUM(premium) AS prem')
                ->get();
        } else {
            $rows = DB::table('policy_reinsurance as pr')
                ->leftJoin('reinsurance_group as g', 'g.id', '=', 'pr.group_id')
                ->where('pr.action_id', $actionId)
                ->whereNull('pr.deleted_at')
                ->groupBy('g.group_code')
                ->selectRaw(
                    "COALESCE(g.group_code,'(none)') AS k, "
                    . "SUM(COALESCE(REPLACE(pr.treatySI, ',', ''), 0)) AS si, "
                    . "SUM(COALESCE(REPLACE(pr.treatyPremium, ',', ''), 0)) AS prem"
                )
                ->get();
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->k] = [
                'ceded_si'      => round((float) $r->si, 2),
                'ceded_premium' => round((float) $r->prem, 2),
            ];
        }

        return $out;
    }

    /**
     * A policy's cession split by layer, at the grain the reinsurance tab shows.
     *
     * THE LEGACY BRANCH IS THE TAB'S OWN QUERY, MOVED NOT REWRITTEN. It splits
     * on type_id -- 3 net retention, 1 quota share, 4 surplus, anything else
     * (including null) facultative -- and that mapping is carried across
     * verbatim so the tab renders exactly what it rendered before. A rewrite
     * here would be a second implementation free to drift from the first.
     *
     * THE REGULATORY BRANCH HAS NO FORMULA, AND SAYS SO. Legacy rows carry a
     * formula_name because a formula produced them; a regulatory layer is
     * produced by the treaty structure itself, so the column comes back null
     * rather than filled with something plausible. The same is true of
     * type_id. A blank that admits it is better than a value that traces to
     * nothing.
     *
     * Auto FAC and FAC are reported together as FACULTATIVE, which is what the
     * tab has always called that column. Outside-treaty has no column on the
     * tab at all and is returned separately so it cannot be silently folded
     * into facultative -- they are different things: facultative is capacity
     * awaiting a placement, outside-treaty is exposure with none behind it.
     *
     * @return array<int,object>
     */
    public function layerRowsForPolicy(int $policyId): array
    {
        if (! $this->isRegulatory()) {
            return DB::select(DB::raw("
                SELECT T.policyNumber, T.risk_id, T.address_name, T.coverage_id,
                       T.group_code, T.group_id, T.formula_name,
                       T.totalSumInsured, T.totalPremium,
                       SUM(T.NETRETENTION)     AS NETRETENTION,
                       SUM(T.QUOTASHARING)     AS QUOTASHARING,
                       SUM(T.SURPLUS)          AS SURPLUS,
                       SUM(T.FACULTATIVE)      AS FACULTATIVE,
                       0                       AS OUTSIDE_TREATY,
                       SUM(T.NETRETENTION_SI)  AS NETRETENTION_SI,
                       SUM(T.QUOTASHARING_SI)  AS QUOTASHARING_SI,
                       SUM(T.SURPLUS_SI)       AS SURPLUS_SI,
                       SUM(T.FACULTATIVE_SI)   AS FACULTATIVE_SI,
                       0                       AS OUTSIDE_TREATY_SI
                FROM (
                    SELECT pol.policyNumber, prm.risk_id, risk.address_name, prm.coverage_id,
                        gm.group_code, gm.id AS group_id, fm.formula_name,
                        prm.totalSumInsured, prm.totalPremium,
                        IF(TRIM(prm.type_id)=3, prm.treatyPremium, 0)                 AS NETRETENTION,
                        IF(TRIM(prm.type_id)=1, prm.treatyPremium, 0)                 AS QUOTASHARING,
                        IF(TRIM(prm.type_id)=4, prm.treatyPremium, 0)                 AS SURPLUS,
                        IF(TRIM(COALESCE(prm.type_id,5))=5, prm.treatyPremium, 0)     AS FACULTATIVE,
                        IF(TRIM(prm.type_id)=3, prm.treatySI, 0)                      AS NETRETENTION_SI,
                        IF(TRIM(prm.type_id)=1, prm.treatySI, 0)                      AS QUOTASHARING_SI,
                        IF(TRIM(prm.type_id)=4, prm.treatySI, 0)                      AS SURPLUS_SI,
                        IF(TRIM(COALESCE(prm.type_id,5))=5, prm.treatySI, 0)          AS FACULTATIVE_SI
                    FROM policy_reinsurance prm
                    LEFT JOIN policies pol           ON pol.id  = prm.policy_id
                    LEFT JOIN reinsurance_group gm   ON gm.id   = prm.group_id
                    LEFT JOIN reinsurance_formula fm ON fm.id   = prm.formula_id
                    LEFT JOIN risk_address risk      ON risk.id = prm.risk_id
                    WHERE prm.policy_id = ?
                      AND prm.treatyPremium <> 0
                      AND prm.deleted_at IS NULL
                ) AS T
                GROUP BY T.risk_id, T.group_code
            "), [$policyId]);
        }

        // Regulatory: the layer IS the split, so no type_id arithmetic.
        return DB::select(DB::raw("
            SELECT p.policyNumber,
                   NULL AS risk_id,
                   r.risk_address AS address_name,
                   NULL AS coverage_id,
                   r.group_code,
                   r.group_id,
                   NULL AS formula_name,
                   SUM(r.sum_insured) AS totalSumInsured,
                   SUM(r.premium)     AS totalPremium,
                   SUM(CASE WHEN r.layer='net_retention'  THEN r.premium     ELSE 0 END) AS NETRETENTION,
                   SUM(CASE WHEN r.layer='quota_share'    THEN r.premium     ELSE 0 END) AS QUOTASHARING,
                   SUM(CASE WHEN r.layer='surplus'        THEN r.premium     ELSE 0 END) AS SURPLUS,
                   SUM(CASE WHEN r.layer IN ('auto_fac','fac') THEN r.premium ELSE 0 END) AS FACULTATIVE,
                   SUM(CASE WHEN r.layer='outside_treaty' THEN r.premium     ELSE 0 END) AS OUTSIDE_TREATY,
                   SUM(CASE WHEN r.layer='net_retention'  THEN r.sum_insured ELSE 0 END) AS NETRETENTION_SI,
                   SUM(CASE WHEN r.layer='quota_share'    THEN r.sum_insured ELSE 0 END) AS QUOTASHARING_SI,
                   SUM(CASE WHEN r.layer='surplus'        THEN r.sum_insured ELSE 0 END) AS SURPLUS_SI,
                   SUM(CASE WHEN r.layer IN ('auto_fac','fac') THEN r.sum_insured ELSE 0 END) AS FACULTATIVE_SI,
                   SUM(CASE WHEN r.layer='outside_treaty' THEN r.sum_insured ELSE 0 END) AS OUTSIDE_TREATY_SI
            FROM policy_reinsurance_regulatory r
            LEFT JOIN policies p ON p.id = r.policy_id
            WHERE r.policy_id = ?
              AND r.deleted_at IS NULL
            GROUP BY p.policyNumber, r.risk_address, r.group_code, r.group_id
        "), [$policyId]);
    }

    /**
     * Motor groups, as both tab implementations hard-code them.
     *
     * Carried across rather than derived, because deriving them would change
     * which rows land in which branch and this is a move, not a rewrite. They
     * are the same five ids in graphiteBWV8, TmpTable and the v2 API.
     */
    public const MOTOR_GROUP_IDS = [13, 14, 15, 28, 29];

    /**
     * The cession for one ACTION, split by layer, as the reinsurance tab shows.
     *
     * TmpTable (Livewire) and PolicyController::reinsurance() (v2 API) run the
     * SAME PAIR of queries, so one method serves both. Motor is grouped by
     * coverage; everything else by risk address and group.
     *
     * MOVED, NOT REWRITTEN -- including the parts that look wrong:
     *
     *   · The motor branch USED SUM(DISTINCT), which collapsed two vehicles
     *     carrying identical figures into one. It was moved verbatim first and
     *     corrected afterwards, once the move itself was proved safe -- see the
     *     note on the motor query below for what it was costing.
     *   · The non-motor branch drops rows with a nil treaty premium or a nil sum
     *     insured; the motor branch does not.
     *   · type_id 6 is a sixth bucket, Facultative Placement, which the older
     *     Livewire Table did not have.
     *
     * WHY THERE IS NO SQLITE TEST FOR THIS. The split turns on
     * IF(TRIM(type_id)=3, ...). MySQL coerces TRIM's string back to a number and
     * matches; sqlite does not and returns nil for every bucket. A sqlite test
     * would pass while asserting the opposite of production. Equivalence is
     * proved instead against real data --
     * database/manual/prove_cession_seam_equivalence.php.
     *
     * @return array{motor:array<int,object>,non_motor:array<int,object>}
     */
    public function layerRowsForAction(int $policyId, int $actionId): array
    {
        $motorIds = implode(',', self::MOTOR_GROUP_IDS);

        if ($this->isRegulatory()) {
            return [
                'motor'     => $this->regulatoryActionRows($policyId, $actionId, true),
                'non_motor' => $this->regulatoryActionRows($policyId, $actionId, false),
            ];
        }

        $baseSelect = "
            pol.policyNumber, prm.risk_id, risk.address_name, gm.group_code,
            gm.id as group_id, fm.formula_name, tr.treaty_name, tr.treaty_number,
            prm.coverage_id, prm.totalSumInsured, prm.totalPremium,
            IF(TRIM(prm.type_id)=3, prm.treatyPremium, 0) AS NETRETENTION,
            IF(TRIM(prm.type_id)=1, prm.treatyPremium, 0) AS QUOTASHARING,
            IF(TRIM(prm.type_id)=4, prm.treatyPremium, 0) AS SURPLUS,
            IF(TRIM(COALESCE(prm.type_id,5))=5, prm.treatyPremium, 0) AS FACULTATIVE,
            IF(TRIM(COALESCE(prm.type_id,6))=6, prm.treatyPremium, 0) AS FACULATIVEPLACEMENT,
            IF(TRIM(prm.type_id)=3, prm.treatySI, 0) AS NETRETENTION_SI,
            IF(TRIM(prm.type_id)=1, prm.treatySI, 0) AS QUOTASHARING_SI,
            IF(TRIM(prm.type_id)=4, prm.treatySI, 0) AS SURPLUS_SI,
            IF(TRIM(COALESCE(prm.type_id,5))=5, prm.treatySI, 0) AS FACULTATIVE_SI,
            IF(TRIM(COALESCE(prm.type_id,6))=6, prm.treatySI, 0) AS FACULATIVEPLACEMENT_SI
        ";

        $baseFrom = "
            FROM policy_reinsurance prm
            LEFT JOIN policies pol ON pol.id = prm.policy_id
            LEFT JOIN reinsurance_group gm ON gm.id = prm.group_id
            LEFT JOIN reinsurance_formula fm ON fm.id = prm.formula_id
            LEFT JOIN reinsurance_treaty tr ON tr.id = prm.treaty_id
            LEFT JOIN risk_address risk ON risk.id = prm.risk_id
            WHERE prm.policy_id = ? AND prm.action_id = ? AND prm.deleted_at IS NULL
        ";

        $cols = "T.policyNumber, T.risk_id, T.address_name, T.coverage_id,
                 T.group_code, T.group_id, T.formula_name,
                 T.treaty_name, T.treaty_number, T.totalSumInsured, T.totalPremium";

        $buckets = ['NETRETENTION', 'QUOTASHARING', 'SURPLUS', 'FACULTATIVE',
                    'FACULATIVEPLACEMENT', 'NETRETENTION_SI', 'QUOTASHARING_SI',
                    'SURPLUS_SI', 'FACULTATIVE_SI', 'FACULATIVEPLACEMENT_SI'];

        $sums = fn (string $agg) => implode(', ', array_map(
            fn ($b) => "{$agg}(T.{$b}) AS {$b}",
            $buckets
        ));

        /*
         * PLAIN SUM, NOT SUM(DISTINCT). Both tabs used SUM(DISTINCT) here, which
         * collapses two vehicles carrying identical figures into one: a fleet
         * with three identical vans under one coverage reported one van's
         * cession.
         *
         * It was checked before removing, in case it was guarding a join
         * fan-out. It was not -- the five joins produce exactly one row per
         * source row, 2,687 of each across the motor book. Measured on the same
         * book, the DISTINCT was losing 35,249.78 of net retention premium and
         * 149,047.67 of quota share.
         */
        $motorSql = "SELECT {$cols}, " . $sums('SUM') . "
            FROM (SELECT {$baseSelect} {$baseFrom} AND gm.id IN({$motorIds})) AS T
            GROUP BY T.coverage_id";

        $nonMotorSql = "SELECT {$cols}, " . $sums('SUM') . "
            FROM (SELECT {$baseSelect} {$baseFrom}
                AND gm.id NOT IN({$motorIds})
                AND prm.treatyPremium != 0
                AND prm.totalSumInsured != 0) AS T
            GROUP BY T.risk_id, T.group_code";

        $params = [$policyId, $actionId];

        return [
            'motor'     => DB::select($motorSql, $params),
            'non_motor' => DB::select($nonMotorSql, $params),
        ];
    }

    /**
     * The same shape on the regulatory basis.
     *
     * Facultative Placement has no regulatory equivalent -- type 6 is a legacy
     * formula type, not a treaty layer -- so it comes back nil rather than
     * borrowing the facultative figure.
     *
     * @return array<int,object>
     */
    private function regulatoryActionRows(int $policyId, int $actionId, bool $motor): array
    {
        $ids = implode(',', self::MOTOR_GROUP_IDS);
        $in  = $motor ? "IN({$ids})" : "NOT IN({$ids})";
        $grp = $motor ? 'r.group_code' : 'r.risk_address, r.group_code';

        return DB::select(DB::raw("
            SELECT p.policyNumber, NULL AS risk_id, r.risk_address AS address_name,
                   NULL AS coverage_id, r.group_code, r.group_id,
                   NULL AS formula_name, NULL AS treaty_name, NULL AS treaty_number,
                   SUM(r.sum_insured) AS totalSumInsured, SUM(r.premium) AS totalPremium,
                   SUM(CASE WHEN r.layer='net_retention'       THEN r.premium     ELSE 0 END) AS NETRETENTION,
                   SUM(CASE WHEN r.layer='quota_share'         THEN r.premium     ELSE 0 END) AS QUOTASHARING,
                   SUM(CASE WHEN r.layer='surplus'             THEN r.premium     ELSE 0 END) AS SURPLUS,
                   SUM(CASE WHEN r.layer IN ('auto_fac','fac') THEN r.premium     ELSE 0 END) AS FACULTATIVE,
                   0                                                                          AS FACULATIVEPLACEMENT,
                   SUM(CASE WHEN r.layer='net_retention'       THEN r.sum_insured ELSE 0 END) AS NETRETENTION_SI,
                   SUM(CASE WHEN r.layer='quota_share'         THEN r.sum_insured ELSE 0 END) AS QUOTASHARING_SI,
                   SUM(CASE WHEN r.layer='surplus'             THEN r.sum_insured ELSE 0 END) AS SURPLUS_SI,
                   SUM(CASE WHEN r.layer IN ('auto_fac','fac') THEN r.sum_insured ELSE 0 END) AS FACULTATIVE_SI,
                   0                                                                          AS FACULATIVEPLACEMENT_SI
            FROM policy_reinsurance_regulatory r
            LEFT JOIN policies p ON p.id = r.policy_id
            WHERE r.policy_id = ? AND r.action_id = ? AND r.deleted_at IS NULL
              AND COALESCE(r.group_id, 0) {$in}
            GROUP BY p.policyNumber, {$grp}
        "), [$policyId, $actionId]);
    }

    /**
     * The risk units FacCoverageService rolls up, as a QUERY rather than rows.
     *
     * A BUILDER, NOT A RESULT SET, and deliberately. scan() rolls these up for
     * the whole book inside one statement with a joinSub; handing it rows would
     * turn one query into one per policy, which on a 5,000-policy scan is a
     * regression nobody would thank us for. shortfallFor() takes the same
     * builder scoped to a single policy and action.
     *
     * WHAT A UNIT IS. Legacy stores one row per (group, formula, coverage), so
     * a coverage running four layers repeats its sum insured four times: risk_si
     * takes MAX and treaty_si takes SUM. The regulatory basis stores one row per
     * layer of a risk address, so both are SUM -- the layers do not repeat the
     * sum insured, they partition it.
     *
     * TREATY_SI IS EVERYTHING THE PROGRAMME ABSORBED, which on the regulatory
     * basis means every layer except outside-treaty. That mirrors the legacy
     * definition in this service's own docblock -- "risk sum insured less
     * (retention + cession + any layer)" -- and it means Auto FAC and FAC count
     * as absorbed on both bases. Whether they are actually PLACED is a different
     * question and the one her work paper's open item 8 raises; it is not this
     * figure.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function riskUnitsQuery(
        ?int $policyId = null,
        ?int $actionId = null,
        bool $latestActionPerPolicy = false
    ) {
        if (! $this->isRegulatory()) {
            $si  = "CAST(REPLACE(COALESCE(pr.totalSumInsured,'0'), ',', '') AS DECIMAL(20,2))";
            $prm = "CAST(REPLACE(COALESCE(pr.totalPremium,'0'),    ',', '') AS DECIMAL(20,2))";
            $tsi = "CAST(REPLACE(COALESCE(pr.treatySI,'0'),        ',', '') AS DECIMAL(20,2))";
            $tpr = "CAST(REPLACE(COALESCE(pr.treatyPremium,'0'),   ',', '') AS DECIMAL(20,2))";

            $q = DB::table('policy_reinsurance as pr')
                ->leftJoin('reinsurance_group as gm', 'gm.id', '=', 'pr.group_id')
                ->whereNull('pr.deleted_at')
                ->groupBy('pr.policy_id', 'pr.group_id', 'gm.group_code', 'pr.coverage_id')
                ->select(
                    'pr.policy_id',
                    'pr.group_id',
                    'gm.group_code',
                    'pr.coverage_id',
                    DB::raw("MAX({$si})  as risk_si"),
                    DB::raw("MAX({$prm}) as risk_premium"),
                    DB::raw("SUM({$tsi}) as treaty_si"),
                    DB::raw("SUM({$tpr}) as treaty_premium")
                );

            if ($policyId !== null) {
                $q->where('pr.policy_id', $policyId);
            }
            if ($actionId !== null) {
                $q->where('pr.action_id', $actionId);
            }
            if ($latestActionPerPolicy) {
                $q->joinSub(
                    DB::table('policy_reinsurance')
                        ->whereNull('deleted_at')
                        ->select('policy_id', DB::raw('MAX(action_id) as action_id'))
                        ->groupBy('policy_id'),
                    'l',
                    fn ($j) => $j->on('l.policy_id', '=', 'pr.policy_id')
                                 ->on('l.action_id', '=', 'pr.action_id')
                );
            }

            return $q;
        }

        $absorbed = "CASE WHEN r.layer <> 'outside_treaty' THEN %s ELSE 0 END";

        $q = DB::table('policy_reinsurance_regulatory as r')
            ->whereNull('r.deleted_at')
            ->groupBy('r.policy_id', 'r.group_id', 'r.group_code', 'r.risk_address')
            ->select(
                'r.policy_id',
                'r.group_id',
                'r.group_code',
                DB::raw('NULL as coverage_id'),
                DB::raw('SUM(r.sum_insured) as risk_si'),
                DB::raw('SUM(r.premium)     as risk_premium'),
                DB::raw('SUM(' . sprintf($absorbed, 'r.sum_insured') . ') as treaty_si'),
                DB::raw('SUM(' . sprintf($absorbed, 'r.premium') . ') as treaty_premium')
            );

        if ($policyId !== null) {
            $q->where('r.policy_id', $policyId);
        }
        if ($actionId !== null) {
            $q->where('r.action_id', $actionId);
        }
        // The alias differs by branch, which is exactly why the caller cannot
        // express this itself and the seam has to own it.
        if ($latestActionPerPolicy) {
            $q->joinSub(
                DB::table('policy_reinsurance_regulatory')
                    ->whereNull('deleted_at')
                    ->select('policy_id', DB::raw('MAX(action_id) as action_id'))
                    ->groupBy('policy_id'),
                'l',
                fn ($j) => $j->on('l.policy_id', '=', 'r.policy_id')
                             ->on('l.action_id', '=', 'r.action_id')
            );
        }

        return $q;
    }

    /**
     * Actions that have never been persisted on the regulatory basis.
     *
     * Run this BEFORE flipping the flag, not after. Every action listed here
     * would report nil cession the moment the basis changes.
     *
     * @return int[]
     */
    public function unpersistedActions(): array
    {
        return DB::table('policy_reinsurance_details as d')
            ->distinct()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('policy_reinsurance_regulatory as r')
                  ->whereColumn('r.action_id', 'd.action_id')
                  ->whereNull('r.deleted_at');
            })
            ->orderBy('d.action_id')
            ->pluck('d.action_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Refuse to report nil for an action that was simply never run.
     *
     * An action with a staged cession but no regulatory rows has not been
     * allocated. Nil is a legitimate answer only when the allocation exists and
     * genuinely cedes nothing.
     */
    /**
     * The most recent action on a policy that actually carries an allocation.
     *
     * WHY THIS IS ON THE SEAM AND NOT AT THE CALL SITE. ClaimsV2Controller
     * picked this action out of policy_reinsurance and then read its figures.
     * Moving only the figures would leave the two halves on different bases:
     * the action chosen because LEGACY holds rows for it, the money read from
     * the REGULATORY table -- which may hold none for that action and would
     * raise, or worse hold some for a different one. The choice and the read
     * have to agree about which basis they are on, so both live here.
     *
     * Null means no allocation on the live basis, which the caller already
     * handles: the endpoint says so plainly rather than reporting nil cession.
     */
    public function latestAllocatedActionFor(int $policyId): ?int
    {
        $table = $this->isRegulatory()
            ? 'policy_reinsurance_regulatory'
            : 'policy_reinsurance';

        $id = DB::table($table)
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('action_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * One action's allocation AGGREGATED per treaty and layer.
     *
     * ClaimsV2Controller's RI-recovery breakdown reads this. It was the last
     * aggregate still reaching into policy_reinsurance directly, and on the
     * legacy basis it returns exactly what that query returned -- the SQL below
     * is that query, moved rather than rewritten, casts included.
     *
     * THE CASTS NOW STRIP THE COMMA, which the query they were moved from did
     * not. treatySI and treatyPremium are varchar and elsewhere in this class
     * are read through REPLACE(...,',','') because a thousands separator can
     * reach them. A bare CAST AS DECIMAL stops at the first comma, so a
     * "1,234.56" row would contribute 1.00 -- a figure wrong by a factor of a
     * thousand and entirely plausible on the page.
     *
     * MOVED FIRST, FIXED SECOND, DELIBERATELY. The bare casts were carried
     * across verbatim when this read moved onto the seam, because correcting
     * them in the same commit would have made the move unprovable: if the
     * figures shifted, nobody could say whether the seam or the fix did it. The
     * move is proved, so the fix lands on its own.
     *
     * IT MOVES NO FIGURE TODAY. No row in policy_reinsurance carries a comma --
     * 0 of 3,573 on the live book, checked before the change -- so this is a
     * latent fault closed while closing it is free, rather than after an import
     * puts a comma there and the claims recovery breakdown understates in
     * silence. An earlier version of this comment claimed the old behaviour was
     * pinned in a test. It was not; the test exists now.
     *
     * PERCENTAGE HAS NO REGULATORY EQUIVALENT. Legacy stores treatyPercentage
     * per row; the regulatory table stores the money and derives the share. It
     * comes back null on that basis rather than a figure traced to nothing --
     * the same rule formula_name and type_id already follow in this class.
     *
     * @return array<int,object>
     */
    public function allocationTotalsForAction(int $policyId, int $actionId): array
    {
        if (! $this->isRegulatory()) {
            return DB::select(DB::raw("
                SELECT pr.treaty_id,
                       t.treaty_name,
                       pr.type_id,
                       rt.type_name,
                       rt.type_code,
                       -- REPLACE before CAST, not a bare CAST. See the docblock.
                       SUM(CAST(REPLACE(COALESCE(pr.treatyPercentage,'0'), ',', '') AS DECIMAL(15,6))) AS pct_sum,
                       SUM(CAST(REPLACE(COALESCE(pr.treatyPremium,'0'),    ',', '') AS DECIMAL(15,2))) AS premium_sum,
                       SUM(CAST(REPLACE(COALESCE(pr.treatySI,'0'),         ',', '') AS DECIMAL(15,2))) AS si_sum
                FROM policy_reinsurance pr
                LEFT JOIN reinsurance_treaty t ON t.id = pr.treaty_id
                LEFT JOIN reinsurance_type  rt ON rt.id = pr.type_id
                WHERE pr.policy_id = ? AND pr.action_id = ? AND pr.deleted_at IS NULL
                GROUP BY pr.treaty_id, t.treaty_name, pr.type_id, rt.type_name, rt.type_code
            "), [$policyId, $actionId]);
        }

        $this->guardPersisted($actionId);

        return DB::select(DB::raw("
            SELECT r.treaty_id,
                   t.treaty_name,
                   NULL AS type_id,
                   " . self::LAYER_NAME_SQL . " AS type_name,
                   " . self::LAYER_CODE_SQL . " AS type_code,
                   NULL               AS pct_sum,
                   SUM(r.premium)     AS premium_sum,
                   SUM(r.sum_insured) AS si_sum
            FROM policy_reinsurance_regulatory r
            LEFT JOIN reinsurance_treaty t ON t.id = r.treaty_id
            WHERE r.policy_id = ? AND r.action_id = ? AND r.deleted_at IS NULL
            GROUP BY r.treaty_id, t.treaty_name, " . self::LAYER_CODE_SQL . ", " . self::LAYER_NAME_SQL . "
        "), [$policyId, $actionId]);
    }

    /**
     * One action's allocation as INDIVIDUAL ROWS, for the underwriting review.
     *
     * UnderwritingController lists what the allocation currently holds, row by
     * row, rather than a roll-up -- so this cannot be the aggregate above with
     * a different GROUP BY. Aggregating would change that screen on the LEGACY
     * basis, and the whole point of moving a read before the cutover is that
     * nothing changes until the flag does.
     *
     * TWO COLUMNS DO NOT SURVIVE THE CROSSING, and they come back null rather
     * than invented. UsedFormula records which legacy formula emitted the row;
     * a regulatory layer is produced by the treaty structure itself and no
     * formula ran. treatyPercentage is not stored on that basis either. The id
     * IS returned on both, but it is a different table's key -- callers must
     * treat it as a row handle for display, never as a policy_reinsurance id.
     *
     * @return array<int,object>
     */
    public function allocationListForAction(int $policyId, int $actionId): array
    {
        if (! $this->isRegulatory()) {
            return DB::select(DB::raw("
                SELECT pr.id,
                       pr.treaty_id,
                       t.treaty_name,
                       rt.type_name,
                       pr.treatyPercentage AS percentage,
                       pr.treatySI         AS sum_insured,
                       pr.treatyPremium    AS premium,
                       pr.UsedFormula      AS used_formula
                FROM policy_reinsurance pr
                LEFT JOIN reinsurance_treaty t ON t.id = pr.treaty_id
                LEFT JOIN reinsurance_type  rt ON rt.id = pr.type_id
                WHERE pr.policy_id = ? AND pr.action_id = ? AND pr.deleted_at IS NULL
                ORDER BY pr.id
            "), [$policyId, $actionId]);
        }

        $this->guardPersisted($actionId);

        return DB::select(DB::raw("
            SELECT r.id,
                   r.treaty_id,
                   t.treaty_name,
                   " . self::LAYER_NAME_SQL . " AS type_name,
                   NULL           AS percentage,
                   r.sum_insured  AS sum_insured,
                   r.premium      AS premium,
                   NULL           AS used_formula
            FROM policy_reinsurance_regulatory r
            LEFT JOIN reinsurance_treaty t ON t.id = r.treaty_id
            WHERE r.policy_id = ? AND r.action_id = ? AND r.deleted_at IS NULL
            ORDER BY r.id
        "), [$policyId, $actionId]);
    }

    private function guardPersisted(int $actionId): void
    {
        $has = DB::table('policy_reinsurance_regulatory')
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->exists();

        if ($has) {
            return;
        }

        $staged = DB::table('policy_reinsurance_details')
            ->where('action_id', $actionId)
            ->exists();

        if (! $staged) {
            return;   // nothing staged either: genuinely nothing to cede
        }

        throw new RuntimeException(
            "Action {$actionId} carries a staged cession but has no regulatory allocation. "
            . 'Reporting it as nil would understate the reinsurance asset. Run '
            . '"reinsurance:cession-reconciliation --persist" before relying on the regulatory basis.'
        );
    }
}
