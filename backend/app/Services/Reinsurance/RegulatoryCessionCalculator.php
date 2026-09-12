<?php

namespace AlphaDirect\Services\Reinsurance;

use InvalidArgumentException;

/**
 * Cession allocation on the REGULATORY MAPPING basis.
 *
 * The treaty does not read the coverage or the reinsurance coverage group. It
 * reads the regulatory mapping the coverage carries, and the mapping decides the
 * treaty route, whether a class limit caps the cession, and whether the risk earns
 * a vertical layer stack.
 *
 * BASIS AS AT 24 AUGUST 2026 — the review of RI-10, returned by Tlamelo Chimidza.
 * Two instructions of 17 August were amended and both are implemented here:
 *
 *   RI-10 point 10 — "I acknowledge that we may have given incorrect information
 *   on this and would like for the treaty on the listed mappings to a limit."
 *   Motor, Transportation, Miscellaneous and Guarantee are capped again. Between
 *   17 and 24 August they were uncapped, and on one test policy that overstated
 *   ceded sum insured by 229,299,000.
 *
 *   RI-10 points 6 and 11 — "The sum insured on property test should be on a per
 *   risk address basis." The layer stack is built per RISK ADDRESS, not per
 *   reinsurance group. Two Property groups at one address share one stack; one
 *   group spanning two addresses earns two.
 *
 * THE THREE TREATMENTS
 *
 *   Motor, Transportation, Miscellaneous, Guarantee — CAPPED
 *       Retention 30% and cession 70% apply to MIN(sum insured, class limit).
 *       Sum insured above the class limit is retained outside the treaty: neither
 *       slip provides an AutoFAC facility for 2026/27 (Capacities Note 2), so the
 *       excess is uninsured net exposure, not cover.
 *
 *   Property, Engineering — LAYERED
 *       Per risk address. Below the first gross line, a plain 30/70. Above it a
 *       flat retention leg, a flat quota share leg, then surplus, then Auto FAC,
 *       then FAC.
 *
 *   Accident, Liability, unmapped — RETAINED
 *       100% retained and raised as an exception. Confirmed 24 August 2026 as
 *       intentionally unplaced for 2026/27.
 *
 * Two classification rules matter as much as the arithmetic:
 *
 *   - Auto FAC and FAC are NOT cession. There is no book-wide facultative
 *     facility for 2026/27 — each policy carries its own slip with its own terms
 *     and rates — so an unplaced layer is an uninsured net exposure, not cover.
 *     Both sit in the retained column until a placement is recorded.
 *
 *   - The policy-specific FAC register does not feed this calculation. It records
 *     what has been placed against retained exposure; it is not treaty capacity.
 *
 * WHAT IS STILL OPEN, AND WHY THE DEFAULTS ARE WHAT THEY ARE. The reply to point
 * 10 asks for a limit of 10,000,000 and says that conforms to the treaty slip. It
 * does not: Schedule A states 5,000,000 for motor own damage, 1,500,000 for a
 * trailer, 3,000,000 for goods in transit and 1,000,000 each for miscellaneous and
 * fidelity guarantee. Only motor third party damage is 10,000,000. This class
 * therefore defaults to the SIGNED SLIP, which is the evidenced figure, and every
 * limit is a constructor parameter so the flat reading is one line of config if it
 * is confirmed in writing. See RI-11 blocking question 1.
 *
 * Sources: J.B. Boda General Quota Share 2026/27 amended signed slip, Schedule A;
 * J.B. Boda Motor Quota Share 2026/27 Continental Re lead signed slip, Schedule A;
 * RI-TRTY-WP-01 'Programme Structure' section C and 'Allocation Rules' A to C;
 * RI-08 Capacities Table; the review of RI-10 returned 24 August 2026.
 *
 * Pure calculator — no database, no container, no side effects. The engine in
 * PolicyCoverage::getReinsuranceCoverageCalculations() is deliberately NOT wired
 * to this yet; the class limit VALUE and its grain are still open, and cutting
 * over before they are answered would move live cession on an unconfirmed figure.
 */
class RegulatoryCessionCalculator
{
    /** Retained because the mapping carries no treaty route at all. */
    public const ROUTE_RETAINED = 'NOT IN TREATY — 100% retained';

    /**
     * The treaty route each regulatory mapping carries.
     *
     * 'layered' decides between the vertical stack and a flat proportional split.
     * 'class_limit' is the Schedule A capacity that caps a non-layered class; NULL
     * means no cap. A layered mapping takes its cap from the first gross line and
     * the surplus above it, so it carries no class_limit of its own.
     */
    public const ROUTES = [
        'Motor'          => ['treaty' => 'Motor QS',      'layered' => false, 'class_limit' => 5000000.0],
        'Transportation' => ['treaty' => 'General QS',    'layered' => false, 'class_limit' => 3000000.0],
        'Miscellaneous'  => ['treaty' => 'General QS',    'layered' => false, 'class_limit' => 1000000.0],
        'Guarantee'      => ['treaty' => 'General QS',    'layered' => false, 'class_limit' => 1000000.0],
        'Property'       => ['treaty' => 'GQS + Surplus', 'layered' => true,  'class_limit' => null],
        'Engineering'    => ['treaty' => 'GQS + Surplus', 'layered' => true,  'class_limit' => null],
        'Accident'       => ['treaty' => self::ROUTE_RETAINED, 'layered' => false, 'class_limit' => null],
        'Liability'      => ['treaty' => self::ROUTE_RETAINED, 'layered' => false, 'class_limit' => null],
    ];

    /**
     * Schedule A limits that are narrower than their mapping's default, keyed on
     * the reinsurance group. A group named here caps at its own figure.
     *
     * Motor is the case that forces this: MOTOR_COM is motor own damage at
     * 5,000,000 and MOTOR_TRAILERS_COM is a trailer at 1,500,000, and both carry
     * the Motor mapping. A single limit per mapping would silently cede 3,500,000
     * more than the slip gives on every trailer.
     */
    public const GROUP_CLASS_LIMITS = [
        'MOTOR_TRAILERS_COM' => 1500000.0, // Own Damage — any one trailer
        'MOTOR_TRAILERS_DOM' => 1500000.0,
    ];

    /**
     * Groups that do not cede, whatever class their mapping carries.
     *
     * Travel insurance is the case that forces this. Confirmed 29 August 2026:
     * "Travel insurance does not make part of the treaty and should be treated as
     * 100% retained. And it should be classed under Miscellaneous." Miscellaneous
     * is a ceding class, so the mapping alone would cede 70% of every travel risk
     * within the class limit. The mapping is for REPORTING; whether the treaty
     * took the risk is a separate fact, and this is where it lives.
     */
    public const RETAINED_GROUPS = [
        'TRAVELINSURANCE_COM',
        'TRAVELINSURANCE_DOM',
    ];

    /** Retained because the GROUP is outside the treaty, whatever its mapping. */
    public const ROUTE_RETAINED_GROUP = 'NOT IN TREATY — group 100% retained';

    /**
     * 2026/27 treaty parameters. Every one of these is a slip figure, not a
     * default: RI-TRTY-WP-01 references P01 to P09.
     */
    public const DEFAULTS = [
        'first_line'      => 10000000.0, // P03 one gross line
        'retention_leg'   =>  3000000.0, // P01 retention leg of line 1
        'quota_share_leg' =>  7000000.0, // P02 cession leg of line 1
        'surplus'         => 40000000.0, // P04 confirmed in force for 2026/27
        'auto_fac'        => 50000000.0, // P05 capacity
        // Facultative capacity. Confirmed 29 August 2026 against Reinsurance's own
        // working for COMG2026213751: Goods in Transit at 300,000,000 fills Auto FAC
        // to 50,000,000 and FAC to 50,000,000, and reports the remaining 190,000,000
        // as OUTSIDE TREATY. Before that FAC was unbounded, so nothing on any policy
        // could ever be reported as uncovered.
        'fac'             => 50000000.0,
        'retention_pct'   => 0.30,       // P07
        'cession_pct'     => 0.70,       // P06
        /*
         * Ceding commission on ceded premium, confirmed 31 August 2026: "The
         * treaty slips provided by Mr. Beka on the 3 Aug 2026 indicated a 37.5%
         * commission on the ceded premiums and we should abide by the treaty terms
         * on the slips." The Final Terms workbook reads "To be advised" and is
         * superseded by the slip.
         *
         * This is the General Quota Share rate. The surplus carries 30% and Motor
         * runs a sliding scale off its loss ratio, so a policy whose cession spans
         * treaties needs those applied per layer — see config/reinsurance.php
         * 'terms'. Until the statement of account exists, this single rate on the
         * ceded premium is the figure Finance has asked for.
         */
        'ceding_commission_pct' => 0.375,
    ];

    private array $p;

    /** @var array<string,float|null> mapping => class limit, overriding ROUTES. */
    private array $classLimits;

    /** @var array<int,string> group codes that never cede, whatever their mapping. */
    private array $retainedGroups;

    /** @var array<string,float> group => class limit, overriding both. */
    private array $groupLimits;

    /**
     * @param  array<string,mixed>       $params       Layer parameters, overriding DEFAULTS.
     * @param  array<string,float|null>  $classLimits  Per-mapping class limits, overriding ROUTES.
     *                                                 Pass ['Motor' => 10000000.0, ...] for the
     *                                                 flat reading of RI-10 point 10, once it is
     *                                                 confirmed in writing.
     * @param  array<string,float>|null  $groupLimits  Per-group limits, overriding GROUP_CLASS_LIMITS.
     */
    public function __construct(
        array $params = [],
        array $classLimits = [],
        ?array $groupLimits = null,
        ?array $retainedGroups = null
    ) {
        $this->p              = $params + self::DEFAULTS;
        $this->classLimits    = $classLimits;
        $this->groupLimits    = $groupLimits ?? self::GROUP_CLASS_LIMITS;
        $this->retainedGroups = array_map(
            'strtoupper',
            $retainedGroups ?? self::RETAINED_GROUPS
        );

        // The layer map has to agree with its own parameters, or every figure
        // downstream is wrong in a way no check column would catch.
        $line = $this->p['retention_leg'] + $this->p['quota_share_leg'];
        if (abs($line - $this->p['first_line']) > 0.005) {
            throw new InvalidArgumentException(
                'Retention and quota share legs must add to the first gross line: '
                . $this->p['retention_leg'] . ' + ' . $this->p['quota_share_leg']
                . ' != ' . $this->p['first_line']
            );
        }
        if (abs(($this->p['retention_pct'] + $this->p['cession_pct']) - 1.0) > 1e-9) {
            throw new InvalidArgumentException('Retention and cession percentages must add to 1.');
        }

        foreach ($this->classLimits as $mapping => $limit) {
            if ($limit !== null && $limit < 0) {
                throw new InvalidArgumentException("Class limit for {$mapping} cannot be negative.");
            }
        }
    }

    /**
     * True when the GROUP is outside the treaty regardless of its mapping.
     *
     * Distinct from a mapping that does not cede. Accident and Liability carry no
     * treaty route at all; a retained group carries one and is held back from it,
     * so it still reports under its own class.
     */
    public function isRetainedGroup(?string $group): bool
    {
        return $group !== null
            && in_array(strtoupper($group), $this->retainedGroups, true);
    }

    /** True when the mapping earns the vertical layer stack — Property and Engineering. */
    public function isLayered(string $mapping): bool
    {
        return (bool) (self::ROUTES[$mapping]['layered'] ?? false);
    }

    /**
     * The class limit that caps this risk, or NULL for no cap.
     *
     * A group limit beats a mapping limit, because Schedule A words its capacity
     * per class of business and several classes share one regulatory mapping.
     */
    public function classLimitFor(string $mapping, ?string $group = null): ?float
    {
        if ($group !== null && isset($this->groupLimits[$group])) {
            return (float) $this->groupLimits[$group];
        }

        if (array_key_exists($mapping, $this->classLimits)) {
            return $this->classLimits[$mapping] === null ? null : (float) $this->classLimits[$mapping];
        }

        $limit = self::ROUTES[$mapping]['class_limit'] ?? null;

        return $limit === null ? null : (float) $limit;
    }

    /**
     * Whether the sum insured is tested at all — true for every placed mapping.
     *
     * Layered mappings test against the first gross line; capped mappings test
     * against their Schedule A class limit. Only an unplaced mapping, or a capped
     * mapping whose limit has been configured away, escapes the test.
     */
    public function isSumInsuredTested(string $mapping, ?string $group = null): bool
    {
        if (! $this->isInTreaty($mapping)) {
            return false;
        }

        return $this->isLayered($mapping) || $this->classLimitFor($mapping, $group) !== null;
    }

    public function routeFor(string $mapping): string
    {
        return self::ROUTES[$mapping]['treaty'] ?? self::ROUTE_RETAINED;
    }

    public function isInTreaty(string $mapping): bool
    {
        return isset(self::ROUTES[$mapping]) && self::ROUTES[$mapping]['treaty'] !== self::ROUTE_RETAINED;
    }

    /**
     * Split one sum insured into the five vertical components.
     *
     * The components always add back to the sum insured — that identity is what
     * the check columns in the bifurcation workbook prove, and it is the only
     * guarantee that no exposure has been lost or double counted.
     *
     * @return array{net_retention:float,quota_share:float,surplus:float,auto_fac:float,fac:float}
     */
    public function splitSumInsured(float $sumInsured, string $mapping, ?string $group = null): array
    {
        $nil = [
            'net_retention' => 0.0, 'quota_share' => 0.0, 'surplus' => 0.0,
            'auto_fac' => 0.0, 'fac' => 0.0, 'outside_treaty' => 0.0,
        ];

        /*
         * A sum insured of zero or less is a DATA ERROR, not a nil cession.
         *
         * Allocation Rules step 1: "Sum insured <= 0 or blank — EXCEPTION, data
         * error. Suspend cession until corrected." Her test case T18 retains 100%
         * and the premium with it. Returning silent zeros reported the risk as
         * costing nothing and ceding nothing, which reads as settled rather than
         * as broken.
         */
        if ($sumInsured <= 0.0) {
            return ['net_retention' => $sumInsured] + $nil;
        }

        // No treaty route: the whole sum insured is retained net.
        if (! $this->isInTreaty($mapping)) {
            return ['net_retention' => $sumInsured] + $nil;
        }

        // The group is held out of the treaty even though its class cedes. Booked
        // to net retention rather than outside-treaty: the risk was never offered,
        // so there is no unfilled capacity to report against it.
        if ($this->isRetainedGroup($group)) {
            return ['net_retention' => $sumInsured] + $nil;
        }

        if (! $this->isLayered($mapping)) {
            return $this->splitCapped($sumInsured, $mapping, $group) + $nil;
        }

        // Below the first gross line a layered class is a plain proportional split.
        if ($sumInsured <= $this->p['first_line']) {
            return [
                'net_retention'  => $this->p['retention_pct'] * $sumInsured,
                'quota_share'    => $this->p['cession_pct'] * $sumInsured,
                'surplus'        => 0.0,
                'auto_fac'       => 0.0,
                'fac'            => 0.0,
                'outside_treaty' => 0.0,
            ];
        }

        // Above the first line the two legs go flat and the upper layers cascade.
        // Each attachment point is DERIVED from the capacity beneath it, never set
        // as a constant. RI-07 open item 3 asked whether the 100,000,000 FAC
        // attachment is simply 10m + 40m + 50m; it is, and it has to be. Pinning
        // the points at 50m and 100m while the surplus is anything other than
        // 40,000,000 leaves a hole between the layers, and the components stop
        // adding back to the sum insured — exposure that belongs to nobody.
        $surplus = (float) min($sumInsured - $this->p['first_line'], $this->p['surplus']);
        $autoFac = (float) min(max($sumInsured - $this->autoFacAttachesAt(), 0.0), $this->p['auto_fac']);

        /*
         * FAC IS UNBOUNDED ON A LAYERED CLASS, and nothing sits outside the treaty.
         *
         * RI-TRTY-WP-01 'Allocation Rules' section C states the five components as
         * shares of the sum insured and requires them to total exactly 1 for every
         * risk. The last is FAC % = MAX(W - 100,000,000, 0) / W, with no ceiling
         * and no sixth component. Her test case T13 pins it: Property at
         * 250,000,000 gives FAC 60% — 150,000,000 — and a total of 1.
         *
         * This layer WAS capped at 50,000,000 with the balance reported outside the
         * treaty, generalising from the capped classes where her VTEST workbook
         * shows Goods in Transit filling an Auto FAC band, a FAC band, and then
         * 190,000,000 outside. That reading was wrong: the two treatments genuinely
         * differ. A capped class gets bands and a balance; a layered class runs the
         * cascade to the top and the facultative layer absorbs the remainder.
         */
        return [
            'net_retention'  => (float) $this->p['retention_leg'],
            'quota_share'    => (float) $this->p['quota_share_leg'],
            'surplus'        => $surplus,
            'auto_fac'       => $autoFac,
            'fac'            => (float) max($sumInsured - $this->facAttachesAt(), 0.0),
            'outside_treaty' => 0.0,
        ];
    }

    /**
     * Motor, Transportation, Miscellaneous and Guarantee: 30/70 within the class
     * limit, then Auto FAC, then FAC, then outside the treaty.
     *
     * NO SURPLUS. That layer is Property and Engineering only, and the working
     * confirms it: Motor at 34,000,000 and Goods in Transit at 300,000,000 both
     * show nil surplus.
     *
     * THE CASCADE ABOVE THE LIMIT, confirmed 29 August 2026 against Reinsurance's
     * own working for COMG2026213751:
     *
     *   Motor 34,000,000            3m net, 7m QS, 24,000,000 Auto FAC
     *   Goods in Transit 300,000,000 3m net, 7m QS, 50,000,000 Auto FAC,
     *                                50,000,000 FAC, 190,000,000 OUTSIDE TREATY
     *
     * This reverses the earlier reading of Capacities Note 2, which took "no
     * AutoFAC facility" to mean nothing attaches above the class limit at all and
     * booked the whole excess to FAC. On that basis no policy could ever report
     * anything outside the treaty, because the FAC layer was unbounded and
     * absorbed everything.
     *
     * The distinction is not cosmetic. Auto FAC and FAC are capacity that exists
     * and is placed per policy; outside-treaty is exposure nobody carries. They
     * are different lines on a solvency return.
     *
     * @return array{net_retention:float,quota_share:float,surplus:float,auto_fac:float,fac:float,outside_treaty:float}
     */
    private function splitCapped(float $sumInsured, string $mapping, ?string $group): array
    {
        $limit  = $this->classLimitFor($mapping, $group);
        $within = $limit === null ? $sumInsured : min($sumInsured, $limit);
        $above  = max($sumInsured - $within, 0.0);

        $autoFac = (float) min($above, $this->p['auto_fac']);
        $fac     = (float) min($above - $autoFac, $this->p['fac']);

        return [
            'net_retention'  => $this->p['retention_pct'] * $within,
            'quota_share'    => $this->p['cession_pct'] * $within,
            'surplus'        => 0.0,
            'auto_fac'       => $autoFac,
            'fac'            => $fac,
            'outside_treaty' => (float) max($above - $autoFac - $fac, 0.0),
        ];
    }

    /** Surplus exhausts here. On the 2026/27 parameters: 10m + 40m = 50,000,000 (P05). */
    public function autoFacAttachesAt(): float
    {
        return (float) ($this->p['first_line'] + $this->p['surplus']);
    }

    /** Treaty capacity before individual FAC. On the 2026/27 parameters: 100,000,000 (P09). */
    public function facAttachesAt(): float
    {
        return $this->autoFacAttachesAt() + (float) $this->p['auto_fac'];
    }

    /**
     * One risk, fully allocated, with premium apportioned on sum insured.
     *
     * @param  array{sum_insured:float|int|string,premium?:float|int|string,mapping:string,group?:string,risk_address?:string|int|null}  $risk
     */
    public function allocateRisk(array $risk): array
    {
        $si      = (float) ($risk['sum_insured'] ?? 0);
        $premium = (float) ($risk['premium'] ?? 0);
        $mapping = (string) ($risk['mapping'] ?? '');
        $group   = isset($risk['group']) ? (string) $risk['group'] : null;

        $c    = $this->splitSumInsured($si, $mapping, $group);
        $rate = $si > 0.0 ? $premium / $si : 0.0;

        // With no usable sum insured there is no rate to apportion on, but the
        // premium is real and is retained whole — T18.
        $invalidSi = $si <= 0.0;

        // Auto FAC and FAC are retained, not ceded — the classification rule that
        // separates cover from exposure. Outside-treaty is retained too, but it is
        // a third thing again: Auto FAC and FAC are capacity waiting on a
        // placement, outside-treaty is exposure with no capacity behind it at all.
        $cededSi    = $c['quota_share'] + $c['surplus'];
        $unplacedSi = $c['auto_fac'] + $c['fac'];
        $outsideSi  = $c['outside_treaty'];
        $retainedSi = $c['net_retention'] + $unplacedSi + $outsideSi;

        return [
            'group'            => $risk['group'] ?? null,
            'risk_address'     => $risk['risk_address'] ?? null,
            'mapping'          => $mapping,
            'treaty_route'     => $this->isRetainedGroup($group)
                ? self::ROUTE_RETAINED_GROUP
                : $this->routeFor($mapping),
            'layered'          => $this->isLayered($mapping),
            'si_tested'        => $this->isSumInsuredTested($mapping, $group),
            'class_limit'      => $this->isLayered($mapping) ? null : $this->classLimitFor($mapping, $group),
            'in_treaty'        => $this->isInTreaty($mapping) && ! $this->isRetainedGroup($group),
            'sum_insured'      => $si,
            'premium'          => $premium,
            'net_retention_si' => $c['net_retention'],
            'quota_share_si'   => $c['quota_share'],
            'surplus_si'       => $c['surplus'],
            'auto_fac_si'      => $c['auto_fac'],
            'fac_si'           => $c['fac'],
            'outside_treaty_si'=> $outsideSi,
            'ceded_si'         => $cededSi,
            'unplaced_si'      => $unplacedSi,
            'retained_si'      => $retainedSi,
            'ceded_premium'    => $invalidSi ? 0.0 : $cededSi * $rate,
            // What the reinsurer allows back on the premium we ceded. Derived, not
            // stored: it moves with the cession, so holding it separately would
            // only let the two drift.
            'ceding_commission' => $invalidSi
                ? 0.0
                : $cededSi * $rate * (float) $this->p['ceding_commission_pct'],
            'retained_premium' => $invalidSi ? $premium : $retainedSi * $rate,
            'net_premium'      => $c['net_retention'] * $rate,
            'unplaced_premium' => $unplacedSi * $rate,
            'outside_treaty_premium' => $outsideSi * $rate,
            'exception'        => $this->exceptionFor($risk, $mapping),
        ];
    }

    /**
     * A whole policy, allocated per regulatory mapping.
     *
     * THE GRAIN IS THE WHOLE OF THIS METHOD, and it differs by treatment.
     *
     * Layered classes are aggregated to the RISK ADDRESS before the stack is
     * built, confirmed 24 August 2026: "The sum insured on property test should be
     * on a per risk address basis. This is because each risk address will have
     * distinct sum insured on the coverages and ultimately distinct premiums."
     * Two Property groups at one address therefore share one 3m / 7m / 40m stack.
     * Allocating them separately would build two stacks and invent capacity the
     * treaty never gave.
     *
     * Capped classes are allocated PER RISK, because that is how Schedule A words
     * them: "any one motor vehicle", "any one trailer", "per risk", "any one
     * risk". They are deliberately NOT rolled to policy level. Until 24 August
     * they were, and that was safe only because they had no cap. With a cap in
     * force it stops being a reporting choice: rolling up applies ONE class limit
     * to the whole policy instead of one to each risk, and on COMG2026213751 that
     * cedes 3,500,000 of motor where the slip gives 6,300,000 — an understatement
     * of the reinsurance asset, and the mirror image of the error point 10 just
     * corrected. See RI-11 blocking question 2: the review asks for a per risk
     * address test in general terms, which cannot be what the motor slip means,
     * since a vehicle has no risk address.
     *
     * @param  array<int,array{sum_insured:float|int|string,premium?:float|int|string,mapping:string,group?:string,risk_address?:string|int|null}>  $risks
     */
    public function allocatePolicy(array $risks): array
    {
        $units   = [];
        $pending = [];

        foreach ($risks as $i => $risk) {
            $mapping = (string) ($risk['mapping'] ?? '');

            if (! $this->isLayered($mapping)) {
                $units[] = $this->allocateRisk($risk);
                continue;
            }

            // Layered: bucket on the risk address. Where the address is absent we
            // fall back to the group, which reproduces the pre-24-August grain
            // rather than silently merging unrelated situations into one stack —
            // and allocateRisk raises an exception on the row so it stays visible.
            $address = $risk['risk_address'] ?? null;
            $key     = $mapping . '|' . ($address ?? '~group:' . ($risk['group'] ?? $i));

            $pending[$key]['mapping']      = $mapping;
            $pending[$key]['risk_address'] = $address;
            $pending[$key]['group']        = $pending[$key]['group'] ?? ($risk['group'] ?? null);
            $pending[$key]['sum_insured']  = ($pending[$key]['sum_insured'] ?? 0.0) + (float) ($risk['sum_insured'] ?? 0);
            $pending[$key]['premium']      = ($pending[$key]['premium'] ?? 0.0) + (float) ($risk['premium'] ?? 0);
            $pending[$key]['risks']        = ($pending[$key]['risks'] ?? 0) + 1;
            $pending[$key]['groups'][]     = $risk['group'] ?? null;
        }

        foreach ($pending as $agg) {
            $row = $this->allocateRisk([
                'mapping'      => $agg['mapping'],
                'group'        => $agg['group'],
                'risk_address' => $agg['risk_address'],
                'sum_insured'  => $agg['sum_insured'],
                'premium'      => $agg['premium'],
            ]);
            $row['risks']  = $agg['risks'];
            $row['groups'] = array_values(array_unique(array_filter($agg['groups'])));
            $units[]       = $row;
        }

        $byMapping = [];
        foreach ($units as $row) {
            $byMapping[$row['mapping']] = $this->accumulate($byMapping[$row['mapping']] ?? null, $row);
        }

        $totals = [];
        foreach ($byMapping as $row) {
            $totals = $this->accumulate($totals ?: null, $row);
        }

        return [
            'per_risk'   => $units,
            'by_mapping' => $byMapping,
            'totals'     => $totals,
        ];
    }

    /**
     * Why this row needs a human to look at it, or NULL when it does not.
     *
     * A layered risk with no address cannot be tested on the basis confirmed on
     * 24 August. It still allocates — a policy is never blocked for want of
     * reference data — but it must not pass silently.
     */
    private function exceptionFor(array $risk, string $mapping): ?string
    {
        // Allocation Rules step 1, ahead of everything else: an unusable sum
        // insured is a data error and cession must not proceed on it.
        if ((float) ($risk['sum_insured'] ?? 0) <= 0.0) {
            return 'Sum insured is zero, blank or negative — a data error. 100% retained and '
                 . 'cession suspended on this risk until it is corrected at source.';
        }

        if ($this->isRetainedGroup($risk['group'] ?? null)) {
            return 'Group is outside the treaty and 100% retained, though its class cedes. '
                 . 'Report to CFO monthly.';
        }

        if (! $this->isInTreaty($mapping)) {
            return 'Regulatory mapping carries no treaty route — 100% retained. Report to CFO monthly.';
        }

        if ($this->isLayered($mapping) && ($risk['risk_address'] ?? null) === null) {
            return 'Layered class with no risk address — the sum insured test falls back to the '
                 . 'reinsurance group. Confirmed basis is per risk address. Report to CFO monthly.';
        }

        return null;
    }

    /** Sum the money columns, carrying the descriptive ones from the first row seen. */
    private function accumulate(?array $carry, array $row): array
    {
        $money = [
            'sum_insured', 'premium', 'net_retention_si', 'quota_share_si', 'surplus_si',
            'auto_fac_si', 'fac_si', 'outside_treaty_si', 'ceded_si', 'unplaced_si', 'retained_si',
            'ceded_premium', 'ceding_commission', 'retained_premium', 'net_premium',
            'unplaced_premium', 'outside_treaty_premium',
        ];

        if ($carry === null) {
            $carry = array_fill_keys($money, 0.0) + [
                'mapping'      => $row['mapping'],
                'treaty_route' => $row['treaty_route'],
                'layered'      => $row['layered'],
                'si_tested'    => $row['si_tested'],
                'in_treaty'    => $row['in_treaty'],
                'risks'        => 0,
            ];
        }

        foreach ($money as $k) {
            $carry[$k] += (float) ($row[$k] ?? 0.0);
        }
        $carry['risks'] += (int) ($row['risks'] ?? 1);

        return $carry;
    }
}
