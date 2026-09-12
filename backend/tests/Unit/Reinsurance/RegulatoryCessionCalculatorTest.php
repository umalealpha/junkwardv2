<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\RegulatoryCessionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The regulatory-mapping cession rules, pinned to policy COMG2026213751 on the
 * basis AMENDED BY THE RI-10 REVIEW returned 24 August 2026.
 *
 * Two figures moved between RI-10 and this file, and both moved because the
 * instruction moved, not because the arithmetic changed:
 *
 *   Point 10 — Motor, Transportation, Miscellaneous and Guarantee are capped at
 *   their class limit again. RI-10 reported them uncapped and ceded 239,099,000
 *   across the four; on the signed slip they cede 9,800,000.
 *
 *   Points 6 and 11 — the layered classes are tested per RISK ADDRESS.
 *
 * These are not invented numbers. If a change moves any of them, the calculator
 * has stopped agreeing with the basis Finance signed off, and that is a failure
 * however green the rest of the suite is. test_the_uncapped_basis_still_reproduces
 * _ri10_exactly keeps the superseded basis reachable, so the movement between the
 * two can always be evidenced rather than asserted.
 *
 * Pure unit test — no database, no container.
 */
class RegulatoryCessionCalculatorTest extends TestCase
{
    private RegulatoryCessionCalculator $calc;

    /** The ten risks the system reports for COMG2026213751 (RI-09 sheet 1). */
    private const POLICY = [
        ['group' => 'PROPERTYANDBI_COM',        'sum_insured' => 201641212, 'premium' => 2265412.12, 'mapping' => 'Property'],
        ['group' => 'ACCIDENTAL_DAMAGE_COM',    'sum_insured' => 120050000, 'premium' =>  240500.00, 'mapping' => 'Property'],
        ['group' => 'ELECTRONIC_EQ_AND_BI_COM', 'sum_insured' =>  12123000, 'premium' => 1211150.00, 'mapping' => 'Property'],
        ['group' => 'MOTOR_TRADERS_COM_EXT',    'sum_insured' =>  10210000, 'premium' => 1021000.00, 'mapping' => 'Property'],
        ['group' => 'MOTOR_TRADERS_COM_INT',    'sum_insured' =>   2110000, 'premium' =>   30000.00, 'mapping' => 'Property'],
        ['group' => 'GOODSINTRANSIT_COM',       'sum_insured' => 300000000, 'premium' => 3000000.00, 'mapping' => 'Transportation'],
        ['group' => 'MISC_COM',                 'sum_insured' =>   1570000, 'premium' =>   57815.40, 'mapping' => 'Miscellaneous'],
        ['group' => 'FIDELITYG_COM',            'sum_insured' =>   2000000, 'premium' =>   20000.00, 'mapping' => 'Guarantee'],
        ['group' => 'MOTOR_COM',                'sum_insured' =>  34000000, 'premium' =>14000000.00, 'mapping' => 'Motor'],
        ['group' => 'MOTOR_COM',                'sum_insured' =>   4000000, 'premium' =>   40000.00, 'mapping' => 'Motor'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new RegulatoryCessionCalculator();
    }

    // ---------------------------------------------------------------- routing

    public function test_only_the_six_placed_mappings_carry_a_treaty_route(): void
    {
        foreach (['Motor', 'Property', 'Engineering', 'Transportation', 'Miscellaneous', 'Guarantee'] as $m) {
            $this->assertTrue($this->calc->isInTreaty($m), "$m should carry a treaty route");
        }

        // Neither 2026/27 slip names these, so they are retained and reported monthly.
        foreach (['Accident', 'Liability', 'Aviation', ''] as $m) {
            $this->assertFalse($this->calc->isInTreaty($m), "$m should not carry a treaty route");
        }
    }

    /**
     * Every placed mapping is sum insured tested. The layered ones test against
     * the first gross line, the capped ones against their Schedule A limit.
     *
     * Between 17 and 24 August the four capped classes carried no test at all.
     * That is exactly what point 10 withdrew.
     */
    public function test_every_placed_mapping_is_sum_insured_tested(): void
    {
        foreach (['Property', 'Engineering', 'Motor', 'Transportation', 'Miscellaneous', 'Guarantee'] as $m) {
            $this->assertTrue($this->calc->isSumInsuredTested($m), "$m must be sum insured tested");
        }

        // An unplaced mapping is not tested — it is retained whole, untouched.
        $this->assertFalse($this->calc->isSumInsuredTested('Liability'));
        $this->assertFalse($this->calc->isSumInsuredTested('Accident'));
    }

    /** Only Property and Engineering earn the vertical stack; the rest are flat. */
    public function test_only_property_and_engineering_are_layered(): void
    {
        $this->assertTrue($this->calc->isLayered('Property'));
        $this->assertTrue($this->calc->isLayered('Engineering'));

        foreach (['Motor', 'Transportation', 'Miscellaneous', 'Guarantee', 'Liability', ''] as $m) {
            $this->assertFalse($this->calc->isLayered($m), "$m must earn no layer stack");
        }
    }

    /**
     * The class limits are the signed Schedule A figures, not the 10,000,000 the
     * review asked for. The reply says 10,000,000 "would then conform to the
     * treaty slip"; the slip says otherwise, and the slip is the evidence.
     */
    public function test_class_limits_are_the_signed_schedule_a_figures(): void
    {
        $this->assertSame(5000000.0, $this->calc->classLimitFor('Motor'));
        $this->assertSame(3000000.0, $this->calc->classLimitFor('Transportation'));
        $this->assertSame(1000000.0, $this->calc->classLimitFor('Miscellaneous'));
        $this->assertSame(1000000.0, $this->calc->classLimitFor('Guarantee'));

        // Layered classes take their cap from the line and the surplus above it.
        $this->assertNull($this->calc->classLimitFor('Property'));
        $this->assertNull($this->calc->classLimitFor('Engineering'));
    }

    /**
     * A trailer is 1,500,000 under Schedule A while motor own damage is
     * 5,000,000, and both carry the Motor mapping. One limit per mapping would
     * cede 2,450,000 more than the slip gives on every trailer above the limit.
     */
    public function test_a_group_limit_beats_the_mapping_limit(): void
    {
        $this->assertSame(1500000.0, $this->calc->classLimitFor('Motor', 'MOTOR_TRAILERS_COM'));
        $this->assertSame(5000000.0, $this->calc->classLimitFor('Motor', 'MOTOR_COM'));

        $trailer = $this->calc->allocateRisk([
            'group' => 'MOTOR_TRAILERS_COM', 'sum_insured' => 4000000, 'premium' => 40000, 'mapping' => 'Motor',
        ]);
        $vehicle = $this->calc->allocateRisk([
            'group' => 'MOTOR_COM', 'sum_insured' => 4000000, 'premium' => 40000, 'mapping' => 'Motor',
        ]);

        $this->assertSame(1050000.0, $trailer['ceded_si'], '70% of the 1,500,000 trailer limit');
        $this->assertSame(2800000.0, $vehicle['ceded_si'], 'below the 5,000,000 own damage limit, a plain 30/70');
    }

    /**
     * The limit is a constructor parameter, so the flat reading of point 10 is one
     * line of config the day it is confirmed in writing — not a rebuild.
     */
    public function test_the_flat_ten_million_reading_is_reachable_by_parameter(): void
    {
        $flat = new RegulatoryCessionCalculator([], [
            'Motor' => 10000000.0, 'Transportation' => 10000000.0,
            'Miscellaneous' => 10000000.0, 'Guarantee' => 10000000.0,
        ], []);

        $git = $flat->allocateRisk(['sum_insured' => 300000000, 'premium' => 3000000, 'mapping' => 'Transportation']);
        $this->assertSame(7000000.0, $git['ceded_si']);

        // And on the whole policy the two readings differ by 9,499,000 of cession.
        $this->assertEqualsWithDelta(
            9499000.0,
            $flat->allocatePolicy(self::POLICY)['totals']['ceded_si']
                - $this->calc->allocatePolicy(self::POLICY)['totals']['ceded_si'],
            0.005,
            'the cost of the open question in RI-11 point 1'
        );
    }

    public function test_an_unmapped_regulatory_class_is_wholly_retained_and_flagged(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 5000000, 'premium' => 50000, 'mapping' => 'Liability']);

        $this->assertSame(5000000.0, $row['retained_si']);
        $this->assertSame(0.0, $row['ceded_si']);
        $this->assertSame(50000.0, $row['retained_premium']);
        $this->assertNotNull($row['exception'], 'an unplaced mapping must raise an exception');
    }

    // ------------------------------------------- classes with no sum insured test

    /**
     * Goods in Transit carries a 3,000,000 Schedule A limit, so 300,000,000 of sum
     * insured cedes 2,100,000 and leaves 297,000,000 with no cover at all.
     *
     * RI-10 reported 210,000,000 ceded on this one risk. That is the single
     * largest figure point 10 withdrew.
     */
    public function test_transportation_cedes_seventy_percent_of_its_class_limit(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 300000000, 'premium' => 3000000, 'mapping' => 'Transportation']);

        $this->assertSame(2100000.0, $row['quota_share_si']);
        $this->assertSame(2100000.0, $row['ceded_si']);
        $this->assertSame(900000.0, $row['net_retention_si']);
        $this->assertSame(0.0, $row['surplus_si'], 'the General QS classes get no surplus relief');
        $this->assertSame(297900000.0, $row['retained_si']);
        $this->assertEqualsWithDelta(21000.0, $row['ceded_premium'], 0.005);
    }

    /**
     * Sum insured above the class limit is never net retention. Net retention is
     * the 30% leg of a treaty that took the risk; capacity that was never offered
     * is a different thing, and a solvency return does not treat the two alike.
     *
     * ABOVE THE LIMIT IT CASCADES, confirmed 29 August 2026 against Reinsurance's
     * working for COMG2026213751 — Auto FAC to its capacity, then FAC to its
     * capacity, then outside the treaty. Until then the whole excess went to an
     * unbounded FAC layer on the reading that Capacities Note 2 ruled out any
     * AutoFAC facility, and nothing was ever reported outside the treaty at all.
     *
     * Net retention and the retained TOTAL are unmoved by that correction. What
     * changed is how the excess is split between capacity awaiting a placement and
     * exposure with nothing behind it.
     */
    public function test_sum_insured_above_the_class_limit_is_not_a_priced_retention(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 300000000, 'premium' => 3000000, 'mapping' => 'Transportation']);

        $this->assertSame(900000.0, $row['net_retention_si'], 'net retention is 30% of the limit, nothing more');
        $this->assertSame(50000000.0, $row['auto_fac_si'], 'Auto FAC fills first');
        $this->assertSame(50000000.0, $row['fac_si'], 'then FAC');
        $this->assertSame(100000000.0, $row['unplaced_si'], 'capacity awaiting a placement');
        $this->assertSame(197000000.0, $row['outside_treaty_si'], 'exposure with nothing behind it');
        $this->assertSame(297900000.0, $row['retained_si'], 'the retained total is unchanged');
        $this->assertEqualsWithDelta(1000000.0, $row['unplaced_premium'], 0.005);
        $this->assertEqualsWithDelta(1970000.0, $row['outside_treaty_premium'], 0.005);
    }

    public function test_guarantee_and_miscellaneous_are_held_to_their_class_limits(): void
    {
        // Fidelity Guarantee: 1,000,000 per risk. 2,000,000 insured cedes 700,000.
        $fidelity = $this->calc->allocateRisk(['sum_insured' => 2000000, 'premium' => 20000, 'mapping' => 'Guarantee']);
        $this->assertSame(700000.0, $fidelity['ceded_si']);
        $this->assertSame(300000.0, $fidelity['net_retention_si']);
        $this->assertSame(1000000.0, $fidelity['unplaced_si']);
        $this->assertSame(1300000.0, $fidelity['retained_si']);

        // Miscellaneous & Financial Loss: 1,000,000 any one risk.
        $misc = $this->calc->allocateRisk(['sum_insured' => 1570000, 'premium' => 57815.40, 'mapping' => 'Miscellaneous']);
        $this->assertSame(700000.0, $misc['ceded_si']);
        $this->assertSame(870000.0, $misc['retained_si']);
        $this->assertEqualsWithDelta(25777.5669, $misc['ceded_premium'], 0.005);
    }

    // ------------------------------------------------- the property layer stack

    /**
     * PROPERTYANDBI_COM, RI-10 Property Layer Detail row 1. The full cascade:
     * 3m net, 7m quota share, 40m surplus, 50m Auto FAC, 50m FAC, and the balance
     * outside the treaty.
     *
     * FAC IS UNBOUNDED ON A LAYERED CLASS, and nothing sits outside the treaty.
     *
     * It was briefly capped at 50,000,000 on 29 August, generalising from the
     * CAPPED classes where the VTEST workbook shows a band, a band and then a
     * balance. Her work paper settles it the other way for layered classes:
     * Allocation Rules section C gives five components summing to exactly 1, the
     * last being FAC % = MAX(W - 100,000,000, 0) / W with no ceiling. Test case
     * T13 pins it — Property at 250,000,000 gives FAC 60%.
     */
    public function test_property_above_the_line_builds_the_full_layer_stack(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 201641212, 'premium' => 2265412.12, 'mapping' => 'Property']);

        $this->assertSame(3000000.0, $row['net_retention_si']);
        $this->assertSame(7000000.0, $row['quota_share_si']);
        $this->assertSame(40000000.0, $row['surplus_si']);
        $this->assertSame(50000000.0, $row['auto_fac_si']);
        $this->assertSame(101641212.0, $row['fac_si'], 'everything above the Auto FAC layer');
        $this->assertSame(0.0, $row['outside_treaty_si'], 'a layered class reaches the top');

        // Auto FAC and FAC are retained, not ceded — no facility is in force.
        $this->assertSame(47000000.0, $row['ceded_si']);
        $this->assertSame(154641212.0, $row['retained_si']);
    }

    /** Surplus caps at 40,000,000 and only then does Auto FAC begin to fill. */
    public function test_surplus_exhausts_before_auto_fac_attaches(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 120050000, 'premium' => 240500, 'mapping' => 'Property']);

        $this->assertSame(40000000.0, $row['surplus_si']);
        $this->assertSame(50000000.0, $row['auto_fac_si']);
        $this->assertSame(20050000.0, $row['fac_si']);
        $this->assertSame(47000000.0, $row['ceded_si']);
        $this->assertSame(73050000.0, $row['retained_si']);
    }

    /** Just above the line, the excess goes to surplus and nothing reaches Auto FAC. */
    public function test_property_just_above_the_line_uses_surplus_only(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 10210000, 'premium' => 1021000, 'mapping' => 'Property']);

        $this->assertSame(210000.0, $row['surplus_si']);
        $this->assertSame(0.0, $row['auto_fac_si']);
        $this->assertSame(0.0, $row['fac_si']);
        $this->assertSame(7210000.0, $row['ceded_si']);
        $this->assertSame(3000000.0, $row['retained_si']);
    }

    /** At or below the line, Property behaves as a plain 30/70 with no layers. */
    public function test_property_below_the_line_is_a_plain_thirty_seventy(): void
    {
        $row = $this->calc->allocateRisk(['sum_insured' => 2110000, 'premium' => 30000, 'mapping' => 'Property']);

        $this->assertSame(633000.0, $row['net_retention_si']);
        $this->assertSame(1477000.0, $row['quota_share_si']);
        $this->assertSame(0.0, $row['surplus_si']);
        $this->assertSame(1477000.0, $row['ceded_si']);

        $onTheLine = $this->calc->allocateRisk(['sum_insured' => 10000000, 'premium' => 0, 'mapping' => 'Property']);
        $this->assertSame(3000000.0, $onTheLine['net_retention_si']);
        $this->assertSame(7000000.0, $onTheLine['quota_share_si']);
        $this->assertSame(0.0, $onTheLine['surplus_si'], 'exactly on the line takes no surplus');
    }

    // -------------------------------------------------------- the invariant

    /**
     * The SIX components must add back to the sum insured on every risk. This is
     * the identity the RI-10 check columns prove, and the only guarantee that no
     * exposure is lost or counted twice.
     *
     * It was five until 29 August 2026, when FAC gained a ceiling and the balance
     * above every layer became its own component. Summing the old five now leaves
     * the outside-treaty amount out, which is exactly the exposure this identity
     * exists to keep visible.
     */
    public function test_components_reconcile_to_the_sum_insured_on_every_risk(): void
    {
        foreach (self::POLICY as $risk) {
            $row = $this->calc->allocateRisk($risk);
            $sum = $row['net_retention_si'] + $row['quota_share_si'] + $row['surplus_si']
                 + $row['auto_fac_si'] + $row['fac_si'] + $row['outside_treaty_si'];

            $this->assertEqualsWithDelta((float) $risk['sum_insured'], $sum, 0.005, $risk['group'] . ' does not reconcile');
            $this->assertEqualsWithDelta((float) $risk['sum_insured'], $row['ceded_si'] + $row['retained_si'], 0.005);
            $this->assertEqualsWithDelta((float) $risk['premium'], $row['ceded_premium'] + $row['retained_premium'], 0.005);
        }
    }

    /** The identity has to hold across the cascade boundaries, not just on real data. */
    public function test_components_reconcile_across_every_layer_boundary(): void
    {
        foreach ([1, 9999999, 10000000, 10000001, 49999999, 50000000, 50000001, 99999999, 100000000, 100000001, 750000000] as $si) {
            foreach (['Property', 'Motor', 'Guarantee'] as $mapping) {
                $c = $this->calc->splitSumInsured((float) $si, $mapping);
                $this->assertEqualsWithDelta(
                    (float) $si,
                    array_sum($c),
                    0.005,
                    "$mapping at $si does not reconcile"
                );
            }
        }
    }

    /**
     * The identity must survive a change to the surplus. Pinning the Auto FAC and
     * FAC attachment points at 50,000,000 and 100,000,000 while the surplus moves
     * leaves a hole between the layers — 40,000,000 of exposure allocated to nobody
     * when the surplus is switched off. RI-07 open item 3.
     */
    public function test_components_reconcile_for_any_surplus_capacity(): void
    {
        foreach ([0.0, 5000000.0, 40000000.0, 90000000.0] as $surplus) {
            $calc = new RegulatoryCessionCalculator(['surplus' => $surplus]);

            foreach ([10000001, 45000000, 60000000, 201641212, 900000000] as $si) {
                $c = $calc->splitSumInsured((float) $si, 'Property');
                $this->assertEqualsWithDelta(
                    (float) $si,
                    array_sum($c),
                    0.005,
                    "surplus $surplus at sum insured $si does not reconcile"
                );
            }
        }
    }

    /**
     * On the 2026/27 parameters the derived attachment points must land exactly on
     * the figures the documents state: Auto FAC at 50,000,000 (P05) and treaty
     * capacity of 100,000,000 before individual FAC (P09).
     */
    public function test_derived_attachment_points_match_the_slip_figures(): void
    {
        $this->assertSame(50000000.0, $this->calc->autoFacAttachesAt());
        $this->assertSame(100000000.0, $this->calc->facAttachesAt());
    }

    /**
     * An unusable sum insured is a DATA ERROR, retained whole and raised.
     *
     * Allocation Rules step 1: "Sum insured <= 0 or blank — EXCEPTION, data error.
     * Suspend cession until corrected." Her test case T18 retains 100% and the
     * premium with it. This used to return silent zeros, which reported the risk
     * as costing nothing and ceding nothing — settled rather than broken.
     */
    public function test_a_nil_or_negative_sum_insured_is_retained_and_raised(): void
    {
        foreach ([0, -1] as $si) {
            $c = $this->calc->splitSumInsured((float) $si, 'Property');

            // Nothing cedes, and the components still add back to the sum insured.
            $this->assertSame(0.0, $c['quota_share']);
            $this->assertSame(0.0, $c['surplus']);
            $this->assertSame((float) $si, array_sum($c), 'the identity holds either way');
        }

        // The premium is real even where the sum insured is not, so it is retained
        // whole rather than apportioned on a rate that cannot be computed.
        $row = $this->calc->allocateRisk([
            'sum_insured' => 0, 'premium' => 12000, 'mapping' => 'Property',
        ]);

        $this->assertSame(0.0, $row['ceded_premium']);
        $this->assertSame(12000.0, $row['retained_premium']);
        $this->assertStringContainsString('data error', $row['exception']);
        $this->assertStringContainsString('cession suspended', $row['exception']);
    }

    // ------------------------------------------------------- the whole policy

    /**
     * COMG2026213751 on the amended basis: ceded 121,610,000, retained
     * 566,094,212, on 687,704,212 total.
     *
     * RI-10 reported 350,909,000 ceded. The whole of the 229,299,000 movement is
     * the class limit coming back on four mappings — Property is untouched.
     */
    public function test_reproduces_the_amended_policy_totals(): void
    {
        $result = $this->calc->allocatePolicy(self::POLICY);
        $t      = $result['totals'];

        $this->assertEqualsWithDelta(687704212.0, $t['sum_insured'], 0.005);
        $this->assertEqualsWithDelta(21885877.52, $t['premium'], 0.005);

        $this->assertEqualsWithDelta(16833000.0, $t['net_retention_si'], 0.005);
        $this->assertEqualsWithDelta(39277000.0, $t['quota_share_si'], 0.005);
        $this->assertEqualsWithDelta(82333000.0, $t['surplus_si'], 0.005, 'the surplus is unchanged by point 10');
        // Split by the 29 August correction: capacity awaiting a placement, and
        // exposure with nothing behind it. They summed to 549,261,212 when FAC was
        // unbounded, and still do.
        $this->assertEqualsWithDelta(352261212.0, $t['unplaced_si'], 0.005);
        $this->assertEqualsWithDelta(197000000.0, $t['outside_treaty_si'], 0.005);
        $this->assertEqualsWithDelta(549261212.0, $t['unplaced_si'] + $t['outside_treaty_si'], 0.005);

        $this->assertEqualsWithDelta(121610000.0, $t['ceded_si'], 0.005);
        $this->assertEqualsWithDelta(566094212.0, $t['retained_si'], 0.005);
        $this->assertEqualsWithDelta(687704212.0, $t['ceded_si'] + $t['retained_si'], 0.005);

        $this->assertEqualsWithDelta(3798583.9526, $t['ceded_premium'], 0.005);
        $this->assertEqualsWithDelta(18087293.5674, $t['retained_premium'], 0.005);
        $this->assertEqualsWithDelta(21885877.52, $t['ceded_premium'] + $t['retained_premium'], 0.005);
    }

    /**
     * The superseded basis stays reachable by parameter, and still reproduces
     * RI-10 to the cent.
     *
     * This is what lets the movement between the two bases be EVIDENCED on audit
     * rather than asserted. It also proves the class limit is the only thing that
     * moved: switch the caps off and the old workbook comes back exactly.
     */
    public function test_the_uncapped_basis_still_reproduces_ri10_exactly(): void
    {
        $uncapped = new RegulatoryCessionCalculator([], [
            'Motor' => null, 'Transportation' => null, 'Miscellaneous' => null, 'Guarantee' => null,
        ], []);

        $t = $uncapped->allocatePolicy(self::POLICY)['totals'];

        $this->assertEqualsWithDelta(350909000.0, $t['ceded_si'], 0.005, 'RI-10 ceded sum insured');
        $this->assertEqualsWithDelta(336795212.0, $t['retained_si'], 0.005, 'RI-10 retained sum insured');
        $this->assertEqualsWithDelta(115104000.0, $t['net_retention_si'], 0.005);
        // Uncapped, every class runs the layered cascade, so the facultative layer
        // absorbs the lot and nothing is reported outside the treaty.
        $this->assertEqualsWithDelta(221691212.0, $t['unplaced_si'], 0.005);
        $this->assertEqualsWithDelta(0.0, $t['outside_treaty_si'], 0.005);
        $this->assertEqualsWithDelta(14258100.70, $t['ceded_premium'], 0.005);
        $this->assertEqualsWithDelta(7627776.82, $t['retained_premium'], 0.005);

        // And the movement the review is responsible for, stated once.
        $now = $this->calc->allocatePolicy(self::POLICY)['totals'];
        $this->assertEqualsWithDelta(229299000.0, $t['ceded_si'] - $now['ceded_si'], 0.005);
    }

    /** The five mapping rows of the bifurcation sheet, row by row. */
    public function test_reproduces_the_amended_mapping_rows(): void
    {
        $byMapping = $this->calc->allocatePolicy(self::POLICY)['by_mapping'];

        $expected = [
            'Motor'          => ['si' =>  38000000.0, 'ceded' =>   6300000.0, 'retained' =>  31700000.0, 'risks' => 2],
            'Property'       => ['si' => 346134212.0, 'ceded' => 111810000.0, 'retained' => 234324212.0, 'risks' => 5],
            'Transportation' => ['si' => 300000000.0, 'ceded' =>   2100000.0, 'retained' => 297900000.0, 'risks' => 1],
            'Miscellaneous'  => ['si' =>   1570000.0, 'ceded' =>    700000.0, 'retained' =>    870000.0, 'risks' => 1],
            'Guarantee'      => ['si' =>   2000000.0, 'ceded' =>    700000.0, 'retained' =>   1300000.0, 'risks' => 1],
        ];

        // The calculator makes no promise about display order — RI-10 orders the
        // sheet. Compare the set.
        $got = array_keys($byMapping);
        $want = array_keys($expected);
        sort($got);
        sort($want);
        $this->assertSame($want, $got, 'unexpected set of mapping rows');

        foreach ($expected as $mapping => $e) {
            $this->assertEqualsWithDelta($e['si'], $byMapping[$mapping]['sum_insured'], 0.005, "$mapping sum insured");
            $this->assertEqualsWithDelta($e['ceded'], $byMapping[$mapping]['ceded_si'], 0.005, "$mapping ceded");
            $this->assertEqualsWithDelta($e['retained'], $byMapping[$mapping]['retained_si'], 0.005, "$mapping retained");
            $this->assertSame($e['risks'], $byMapping[$mapping]['risks'], "$mapping risk count");
        }
    }

    /**
     * Motor is allocated per vehicle, and rolling it to policy level is now a
     * money error, not a presentation choice.
     *
     * Schedule A words the limit "any one motor vehicle". Two vehicles earn two
     * limits. One 38,000,000 policy-level row would earn one, ceding 3,500,000
     * where the slip gives 6,300,000 — understating the reinsurance asset by
     * 2,800,000 on a two-vehicle policy. Until 24 August the class was uncapped
     * and the two grains agreed, which is why the roll-up was safe then.
     */
    public function test_motor_is_allocated_per_vehicle_not_rolled_to_policy_level(): void
    {
        $motor = array_values(array_filter(self::POLICY, fn ($r) => $r['mapping'] === 'Motor'));

        $row = $this->calc->allocatePolicy($motor)['by_mapping']['Motor'];
        $this->assertSame(2, $row['risks']);
        $this->assertEqualsWithDelta(38000000.0, $row['sum_insured'], 0.005);
        $this->assertEqualsWithDelta(6300000.0, $row['ceded_si'], 0.005);

        $perVehicle = 0.0;
        foreach ($motor as $r) {
            $perVehicle += $this->calc->allocateRisk($r)['ceded_si'];
        }
        $this->assertEqualsWithDelta($perVehicle, $row['ceded_si'], 0.005, 'the roll-up must equal the sum of its parts');

        $asOne = $this->calc->allocateRisk(['sum_insured' => 38000000, 'premium' => 0, 'mapping' => 'Motor']);
        $this->assertEqualsWithDelta(3500000.0, $asOne['ceded_si'], 0.005, 'one limit instead of two');
    }

    // -------------------------------------------------- the risk address grain

    /**
     * Confirmed 24 August 2026: "The sum insured on property test should be on a
     * per risk address basis."
     *
     * Two Property groups at one address share ONE layer stack. Allocating them
     * separately would build two, and cede 11,000,000 where the address earns
     * 9,800,000 — capacity the treaty never gave.
     */
    public function test_layered_classes_share_one_stack_per_risk_address(): void
    {
        $oneAddress = [
            ['group' => 'PROPERTYANDBI_COM',     'sum_insured' => 8000000, 'premium' => 80000, 'mapping' => 'Property', 'risk_address' => 'ADDR-1'],
            ['group' => 'ACCIDENTAL_DAMAGE_COM', 'sum_insured' => 6000000, 'premium' => 60000, 'mapping' => 'Property', 'risk_address' => 'ADDR-1'],
        ];

        $result = $this->calc->allocatePolicy($oneAddress);

        $this->assertCount(1, $result['per_risk'], 'one address is one allocation unit');
        $unit = $result['per_risk'][0];

        $this->assertSame('ADDR-1', $unit['risk_address']);
        $this->assertSame(2, $unit['risks'], 'both groups are counted');
        $this->assertEqualsWithDelta(14000000.0, $unit['sum_insured'], 0.005);
        $this->assertEqualsWithDelta(3000000.0, $unit['net_retention_si'], 0.005);
        $this->assertEqualsWithDelta(7000000.0, $unit['quota_share_si'], 0.005);
        $this->assertEqualsWithDelta(4000000.0, $unit['surplus_si'], 0.005, 'the excess over the line goes to surplus');
        $this->assertEqualsWithDelta(11000000.0, $unit['ceded_si'], 0.005);
        $this->assertNull($unit['exception']);

        // The same money at two addresses earns two stacks and cedes less.
        $twoAddresses = $oneAddress;
        $twoAddresses[1]['risk_address'] = 'ADDR-2';

        $split = $this->calc->allocatePolicy($twoAddresses);
        $this->assertCount(2, $split['per_risk']);
        $this->assertEqualsWithDelta(9800000.0, $split['totals']['ceded_si'], 0.005, '5.6m + 4.2m, each a plain 30/70');
    }

    /** One reinsurance group spanning two addresses earns two stacks, not one. */
    public function test_one_group_across_two_addresses_earns_two_stacks(): void
    {
        $risks = [
            ['group' => 'PROPERTYANDBI_COM', 'sum_insured' => 30000000, 'premium' => 0, 'mapping' => 'Property', 'risk_address' => 'ADDR-1'],
            ['group' => 'PROPERTYANDBI_COM', 'sum_insured' => 30000000, 'premium' => 0, 'mapping' => 'Property', 'risk_address' => 'ADDR-2'],
        ];

        $row = $this->calc->allocatePolicy($risks)['by_mapping']['Property'];

        $this->assertSame(2, $row['risks']);
        $this->assertEqualsWithDelta(6000000.0, $row['net_retention_si'], 0.005, 'two 3m legs');
        $this->assertEqualsWithDelta(14000000.0, $row['quota_share_si'], 0.005, 'two 7m legs');
        $this->assertEqualsWithDelta(40000000.0, $row['surplus_si'], 0.005, 'two 20m surplus draws');
        $this->assertEqualsWithDelta(54000000.0, $row['ceded_si'], 0.005);

        // Tested at group level, as it was before 24 August, the same 60,000,000
        // draws one stack and cedes 47,000,000.
        $asOneGroup = $this->calc->allocateRisk(['sum_insured' => 60000000, 'premium' => 0, 'mapping' => 'Property']);
        $this->assertEqualsWithDelta(47000000.0, $asOneGroup['ceded_si'], 0.005);
    }

    /**
     * A layered risk with no risk address still allocates — a policy is never
     * blocked for want of reference data — but it falls back to the group grain
     * and must say so. Silence here would report the old basis as the new one.
     */
    public function test_a_layered_risk_without_an_address_falls_back_and_is_flagged(): void
    {
        $result = $this->calc->allocatePolicy([
            ['group' => 'PROPERTYANDBI_COM',     'sum_insured' => 8000000, 'premium' => 0, 'mapping' => 'Property'],
            ['group' => 'ACCIDENTAL_DAMAGE_COM', 'sum_insured' => 6000000, 'premium' => 0, 'mapping' => 'Property'],
        ]);

        $this->assertCount(2, $result['per_risk'], 'no address means no merge — the group grain stands');

        foreach ($result['per_risk'] as $row) {
            $this->assertNotNull($row['exception'], 'a layered row with no address must be reported');
            $this->assertStringContainsString('risk address', $row['exception']);
        }

        // A capped class needs no address, so it must NOT be flagged.
        $motor = $this->calc->allocateRisk(['group' => 'MOTOR_COM', 'sum_insured' => 4000000, 'premium' => 0, 'mapping' => 'Motor']);
        $this->assertNull($motor['exception']);
    }

    /** The reconciliation identity has to survive the capped path too. */
    public function test_capped_classes_reconcile_to_the_sum_insured(): void
    {
        foreach (['Motor', 'Transportation', 'Miscellaneous', 'Guarantee'] as $mapping) {
            foreach ([1, 999999, 1000000, 1000001, 3000000, 5000000, 38000000, 300000000] as $si) {
                $c = $this->calc->splitSumInsured((float) $si, $mapping);
                $this->assertEqualsWithDelta(
                    (float) $si,
                    array_sum($c),
                    0.005,
                    "$mapping at $si must reconcile"
                );
            }
        }
    }

    // ------------------------------------------------------ parameter guards

    public function test_a_layer_map_that_disagrees_with_its_parameters_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RegulatoryCessionCalculator(['retention_leg' => 4000000.0]); // 4m + 7m != 10m
    }

    public function test_retention_and_cession_percentages_must_add_to_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RegulatoryCessionCalculator(['retention_pct' => 0.35]); // 0.35 + 0.70 != 1
    }

    /**
     * The surplus is a parameter, not a constant. If the signed surplus slip never
     * arrives it can be set to zero, and the layer collapses to quota share only.
     */
    public function test_the_surplus_layer_can_be_switched_off_by_parameter(): void
    {
        $noSurplus = new RegulatoryCessionCalculator(['surplus' => 0.0]);
        $row = $noSurplus->allocateRisk(['sum_insured' => 201641212, 'premium' => 0, 'mapping' => 'Property']);

        $this->assertSame(0.0, $row['surplus_si']);
        $this->assertSame(7000000.0, $row['ceded_si']);
        $this->assertEqualsWithDelta(194641212.0, $row['retained_si'], 0.005);
    }
}
