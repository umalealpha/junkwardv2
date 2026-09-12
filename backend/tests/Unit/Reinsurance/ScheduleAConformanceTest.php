<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\RegulatoryCessionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests the 2026/27 cession process against the SIGNED SLIPS, not against the
 * instruction that restated it.
 *
 * RegulatoryCessionCalculatorTest proves the calculator reproduces RI-10. That is
 * conformance to an instruction. It says nothing about whether the instruction
 * agrees with the contracts, and it does not: the instruction of 17 August 2026
 * removes the Schedule A class limits for Motor, Miscellaneous, Guarantee and
 * Transportation, and re-routes Accidental Damage and Electronic Equipment into
 * the Property class.
 *
 * DOCUMENT OF RECORD: docs/Alpha-Direct-Capacities-Table-2026-27.xlsx, which states
 * on its own face (Note 10) that it is built solely from the two signed slips — the
 * J.B. Boda General Quota Share amended signed slip and the Motor Quota Share slip
 * with Continental Re as lead. Its Note 1 records that capacity is taken directly
 * from Schedule A of each treaty, that both treaties are 30% retention / 70%
 * cession, and that cession is 70% OF THE CLASS LIMIT. Notes 2 and 3 record that
 * neither treaty establishes a facultative facility or an excess of loss programme,
 * so no AutoFAC and no excess of loss capacity can be stated. There is no surplus
 * column anywhere in the table.
 *
 * WHY THE SCHEDULE A BASIS IS ENCODED HERE AND NOT IN PRODUCT CODE. Which basis
 * governs is unresolved — it is conflict 1 of the mail of 20 August 2026. Shipping a
 * service that implements it would be configuring an unconfirmed rule. It is
 * encoded as a benchmark instead, so the disagreement is measured and pinned rather
 * than argued.
 *
 * These tests PASS. They are not asserting that the restatement is wrong. They
 * assert the SIZE of the gap between the two bases, so that if either side moves,
 * the suite says so.
 *
 * Pure unit test — no database, no container.
 */
class ScheduleAConformanceTest extends TestCase
{
    private const RETENTION_PCT = 0.30;
    private const CESSION_PCT   = 0.70;

    /**
     * Schedule A underwriting limits at 100%, with the basis each is worded on.
     * Capacities Table 2026-27 rows 8 to 19.
     */
    private const SCHEDULE_A = [
        'Damage to Real Property (MD + BI)' => ['limit' => 10000000, 'basis' => 'any one situation'],
        'Engineering combined'              => ['limit' => 10000000, 'basis' => 'combined limit'],
        'Electronic Equipment'              => ['limit' =>  6000000, 'basis' => 'any one situation'],
        'Goods in Transit'                  => ['limit' =>  3000000, 'basis' => 'not stated'],
        'Miscellaneous & Financial Loss'    => ['limit' =>  1000000, 'basis' => 'any one risk'],
        'Fidelity Guarantee'                => ['limit' =>  1000000, 'basis' => 'per risk'],
        'Accidental Damage'                 => ['limit' =>  7500000, 'basis' => 'excluded above the limit'],
        'Motor Own Damage'                  => ['limit' =>  5000000, 'basis' => 'any one motor vehicle'],
        'Motor Own Damage — trailer'        => ['limit' =>  1500000, 'basis' => 'any one trailer'],
        'Passenger Liability'               => ['limit' =>  2500000, 'basis' => 'any one event'],
        'Third Party Damage'                => ['limit' => 10000000, 'basis' => 'any one event'],
    ];

    /** Treaty-level caps on Reinsurers' liability. Capacities Table rows 22 and 26. */
    private const GENERAL_EVENT_LIMIT = 70000000;
    private const MOTOR_EVENT_LIMIT   = 70000000;

    /**
     * COMG2026213751 as the system reports it, with each reinsurance group tied BOTH
     * to its Schedule A class and to the regulatory mapping the instruction assigns.
     */
    private const POLICY = [
        ['group' => 'PROPERTYANDBI_COM',        'si' => 201641212, 'premium' => 2265412.12, 'class' => 'Damage to Real Property (MD + BI)', 'mapping' => 'Property'],
        ['group' => 'ACCIDENTAL_DAMAGE_COM',    'si' => 120050000, 'premium' =>  240500.00, 'class' => 'Accidental Damage',                  'mapping' => 'Property'],
        ['group' => 'ELECTRONIC_EQ_AND_BI_COM', 'si' =>  12123000, 'premium' => 1211150.00, 'class' => 'Electronic Equipment',               'mapping' => 'Property'],
        ['group' => 'MOTOR_TRADERS_COM_EXT',    'si' =>  10210000, 'premium' => 1021000.00, 'class' => 'Third Party Damage',                 'mapping' => 'Property'],
        ['group' => 'MOTOR_TRADERS_COM_INT',    'si' =>   2110000, 'premium' =>   30000.00, 'class' => 'Third Party Damage',                 'mapping' => 'Property'],
        ['group' => 'GOODSINTRANSIT_COM',       'si' => 300000000, 'premium' => 3000000.00, 'class' => 'Goods in Transit',                   'mapping' => 'Transportation'],
        ['group' => 'MISC_COM',                 'si' =>   1570000, 'premium' =>   57815.40, 'class' => 'Miscellaneous & Financial Loss',     'mapping' => 'Miscellaneous'],
        ['group' => 'FIDELITYG_COM',            'si' =>   2000000, 'premium' =>   20000.00, 'class' => 'Fidelity Guarantee',                 'mapping' => 'Guarantee'],
        ['group' => 'MOTOR_COM',                'si' =>  34000000, 'premium' =>14000000.00, 'class' => 'Motor Own Damage',                   'mapping' => 'Motor'],
        ['group' => 'MOTOR_COM',                'si' =>   4000000, 'premium' =>   40000.00, 'class' => 'Motor Own Damage',                   'mapping' => 'Motor'],
    ];

    private const TOTAL_SI      = 687704212.0;
    private const TOTAL_PREMIUM = 21885877.52;

    /**
     * The Schedule A basis: cession is 70% of MIN(sum insured, class limit), and
     * everything above the limit is outside the treaty entirely. No AutoFAC, no
     * excess of loss, no surplus — none is established by either slip.
     */
    private function scheduleACeded(float $si, string $class): float
    {
        return self::CESSION_PCT * min($si, (float) self::SCHEDULE_A[$class]['limit']);
    }

    /**
     * The 17 August instruction basis — the four classes uncapped.
     *
     * This is pinned explicitly rather than taken from the calculator defaults.
     * The review of 24 August withdrew the instruction and the defaults now carry
     * the Schedule A limits, but the conflict this file measures is a matter of
     * record: it is the evidence on which point 10 was raised and answered, and it
     * has to keep reproducing whatever the current defaults happen to be.
     */
    private function instructionBasis(): array
    {
        $calc = new RegulatoryCessionCalculator([], [
            'Motor' => null, 'Transportation' => null, 'Miscellaneous' => null, 'Guarantee' => null,
        ], []);

        return $calc->allocatePolicy($this->risks());
    }

    /** The basis actually shipped: Schedule A limits on the four capped classes. */
    private function currentBasis(): array
    {
        return (new RegulatoryCessionCalculator())->allocatePolicy($this->risks());
    }

    /** @return array<int,array<string,mixed>> */
    private function risks(): array
    {
        return array_map(fn ($r) => [
            'group'       => $r['group'],
            'sum_insured' => $r['si'],
            'premium'     => $r['premium'],
            'mapping'     => $r['mapping'],
        ], self::POLICY);
    }

    // ------------------------------------------------ the documents themselves

    /** Every class in the policy is named in a Schedule A. Note 4 matters if not. */
    public function test_every_class_in_the_policy_is_named_in_a_schedule_a(): void
    {
        foreach (self::POLICY as $r) {
            $this->assertArrayHasKey(
                $r['class'],
                self::SCHEDULE_A,
                $r['group'] . ' maps to a class with no stated treaty capacity (Note 4 — 100% retained)'
            );
        }
    }

    /**
     * Not one Schedule A limit is worded per policy. Every one is per situation,
     * per risk, per vehicle or per event. This is the documentary answer to
     * conflict 3 of the mail.
     */
    public function test_no_schedule_a_limit_is_worded_on_a_per_policy_basis(): void
    {
        foreach (self::SCHEDULE_A as $class => $spec) {
            $this->assertStringNotContainsStringIgnoringCase(
                'policy',
                $spec['basis'],
                "$class is worded on a per policy basis"
            );
        }

        // The four classes the instruction aggregates to policy level are each
        // worded per risk or per vehicle in Schedule A.
        $this->assertSame('any one risk', self::SCHEDULE_A['Miscellaneous & Financial Loss']['basis']);
        $this->assertSame('per risk', self::SCHEDULE_A['Fidelity Guarantee']['basis']);
        $this->assertSame('any one motor vehicle', self::SCHEDULE_A['Motor Own Damage']['basis']);
    }

    // ------------------------------------------------------ the Schedule A basis

    /**
     * The Schedule A basis on this policy: 34,727,000 ceded and 652,977,212
     * retained. Independently cross-checked — these are the figures in the
     * "PER SLIPS" columns of RI-09 sheet 2, which was built before the instruction.
     */
    public function test_schedule_a_basis_reproduces_the_per_slips_figures(): void
    {
        $ceded = 0.0;
        foreach (self::POLICY as $r) {
            $ceded += $this->scheduleACeded((float) $r['si'], $r['class']);
        }

        $this->assertEqualsWithDelta(34727000.0, $ceded, 0.005);
        $this->assertEqualsWithDelta(652977212.0, self::TOTAL_SI - $ceded, 0.005);
        $this->assertEqualsWithDelta(0.0504970, $ceded / self::TOTAL_SI, 0.0000001);
    }

    /** Retention plus cession must exhaust the within-limit sum insured. */
    public function test_schedule_a_retention_and_cession_exhaust_the_within_limit_amount(): void
    {
        foreach (self::POLICY as $r) {
            $within   = min((float) $r['si'], (float) self::SCHEDULE_A[$r['class']]['limit']);
            $retained = self::RETENTION_PCT * $within;
            $ceded    = $this->scheduleACeded((float) $r['si'], $r['class']);

            $this->assertEqualsWithDelta($within, $retained + $ceded, 0.005, $r['group']);
        }
    }

    // --------------------------------------------- the gap between the two bases

    /**
     * The headline of conflict 1. The instruction basis reports 316,182,000 of ceded
     * sum insured above the capacity the signed slips state.
     */
    public function test_the_instruction_basis_exceeds_schedule_a_capacity_by_the_reported_amount(): void
    {
        $scheduleA = 0.0;
        foreach (self::POLICY as $r) {
            $scheduleA += $this->scheduleACeded((float) $r['si'], $r['class']);
        }

        $instruction = $this->instructionBasis()['totals']['ceded_si'];

        $this->assertEqualsWithDelta(34727000.0, $scheduleA, 0.005);
        $this->assertEqualsWithDelta(350909000.0, $instruction, 0.005);
        $this->assertEqualsWithDelta(316182000.0, $instruction - $scheduleA, 0.005);

        // Just over ten times the stated capacity.
        $this->assertGreaterThan(10.0, $instruction / $scheduleA);
    }

    /**
     * Class by class, so the argument is not carried by one outlier. Goods in
     * Transit alone accounts for 207,900,000 of the 316,182,000.
     */
    public function test_the_gap_class_by_class_for_the_four_uncapped_classes(): void
    {
        $expected = [
            // class => [schedule A ceded, instruction ceded]
            'Goods in Transit'               => [ 2100000.0, 210000000.0],
            'Fidelity Guarantee'             => [  700000.0,   1400000.0],
            'Miscellaneous & Financial Loss' => [  700000.0,   1099000.0],
            'Motor Own Damage'               => [ 6300000.0,  26600000.0],
        ];

        $byMapping = $this->instructionBasis()['by_mapping'];
        $mapFor    = [
            'Goods in Transit'               => 'Transportation',
            'Fidelity Guarantee'             => 'Guarantee',
            'Miscellaneous & Financial Loss' => 'Miscellaneous',
            'Motor Own Damage'               => 'Motor',
        ];

        $totalGap = 0.0;

        foreach ($expected as $class => [$schedA, $instr]) {
            $actualScheduleA = 0.0;
            foreach (self::POLICY as $r) {
                if ($r['class'] === $class) {
                    $actualScheduleA += $this->scheduleACeded((float) $r['si'], $r['class']);
                }
            }

            $this->assertEqualsWithDelta($schedA, $actualScheduleA, 0.005, "$class on the Schedule A basis");
            $this->assertEqualsWithDelta($instr, $byMapping[$mapFor[$class]]['ceded_si'], 0.005, "$class on the instruction basis");

            $totalGap += $instr - $schedA;
        }

        $this->assertEqualsWithDelta(229299000.0, $totalGap, 0.005, 'gap on the four uncapped classes');

        // Goods in Transit alone is 207,900,000 of it — 66% of the whole conflict,
        // off a class Schedule A caps at 3,000,000.
        $this->assertEqualsWithDelta(207900000.0, 210000000.0 - 2100000.0, 0.005);
        $this->assertGreaterThan(0.65, 207900000.0 / 316182000.0);
    }

    /**
     * The 316,182,000 must decompose without a remainder, or the figure in the mail
     * is not explainable line by line and cannot be defended in a meeting.
     *
     *   four uncapped classes   229,299,000  (limits removed)
     *   Property-mapped risks    86,883,000  (re-routing + the surplus layer)
     *                           -----------
     *                           316,182,000
     */
    public function test_the_total_gap_decomposes_without_a_remainder(): void
    {
        $uncapped = [
            'Goods in Transit'               => [ 2100000.0, 210000000.0],
            'Fidelity Guarantee'             => [  700000.0,   1400000.0],
            'Miscellaneous & Financial Loss' => [  700000.0,   1099000.0],
            'Motor Own Damage'               => [ 6300000.0,  26600000.0],
        ];
        $propertyMapped = [
            'PROPERTYANDBI_COM'        => [7000000.0, 47000000.0],
            'ACCIDENTAL_DAMAGE_COM'    => [5250000.0, 47000000.0],
            'ELECTRONIC_EQ_AND_BI_COM' => [4200000.0,  9123000.0],
            'MOTOR_TRADERS_COM_EXT'    => [7000000.0,  7210000.0],
            'MOTOR_TRADERS_COM_INT'    => [1477000.0,  1477000.0],
        ];

        $gap = fn (array $rows) => array_sum(array_map(fn ($p) => $p[1] - $p[0], $rows));

        $this->assertEqualsWithDelta(229299000.0, $gap($uncapped), 0.005);
        $this->assertEqualsWithDelta(86883000.0, $gap($propertyMapped), 0.005);
        $this->assertEqualsWithDelta(316182000.0, $gap($uncapped) + $gap($propertyMapped), 0.005);

        // And it must equal the gap the two calculators actually produce.
        $scheduleA = 0.0;
        foreach (self::POLICY as $r) {
            $scheduleA += $this->scheduleACeded((float) $r['si'], $r['class']);
        }
        $this->assertEqualsWithDelta(
            $gap($uncapped) + $gap($propertyMapped),
            $this->instructionBasis()['totals']['ceded_si'] - $scheduleA,
            0.005
        );
    }

    /**
     * WHAT THE GAP HAS BECOME on the basis actually shipped, and why it is not nil.
     *
     * Point 10 closed 229,299,000 of the original 316,182,000. The residual
     * 86,883,000 sits entirely on Property-mapped groups, and it is two distinct
     * questions, not one:
     *
     *   40,000,000  the surplus layer on PROPERTYANDBI_COM. Confirmed in force on
     *               17 August and reconfirmed on 24 August, but no Schedule A row
     *               states a surplus capacity and the signed slip is still
     *               outstanding. An evidence gap, not a rules gap.
     *
     *   46,673,000  Accidental Damage and Electronic Equipment carrying the
     *               Property 10,000,000 line plus surplus, where Schedule A gives
     *               them 7,500,000 and 6,000,000 of their own. This is the same
     *               defect point 10 corrected, on two classes the review did not
     *               name. RI-11 open question 5.
     *
     * If this figure moves, one of those two questions has been answered — and the
     * answer belongs in writing before the number changes here.
     */
    public function test_the_residual_gap_on_the_shipped_basis_is_two_open_questions(): void
    {
        $scheduleA = 0.0;
        foreach (self::POLICY as $r) {
            $scheduleA += $this->scheduleACeded((float) $r['si'], $r['class']);
        }

        $current = $this->currentBasis()['totals']['ceded_si'];

        $this->assertEqualsWithDelta(121610000.0, $current, 0.005, 'shipped basis ceded sum insured');
        $this->assertEqualsWithDelta(86883000.0, $current - $scheduleA, 0.005, 'residual gap to Schedule A');

        // The four capped classes now agree with the slip to the cent. That is the
        // whole of what point 10 bought.
        $byMapping = $this->currentBasis()['by_mapping'];
        $capped    = [
            'Transportation' => 'Goods in Transit',
            'Guarantee'      => 'Fidelity Guarantee',
            'Miscellaneous'  => 'Miscellaneous & Financial Loss',
            'Motor'          => 'Motor Own Damage',
        ];

        foreach ($capped as $mapping => $class) {
            $expected = 0.0;
            foreach (self::POLICY as $r) {
                if ($r['class'] === $class) {
                    $expected += $this->scheduleACeded((float) $r['si'], $r['class']);
                }
            }
            $this->assertEqualsWithDelta(
                $expected,
                $byMapping[$mapping]['ceded_si'],
                0.005,
                "$class must now agree with Schedule A exactly"
            );
        }

        // And the residual decomposes into the two questions without a remainder.
        $surplus          = $this->currentBasis()['totals']['surplus_si'];
        $accidentalAndEe  = (47000000.0 - 5250000.0) + (9123000.0 - 4200000.0);
        $motorTradersExt  = 7210000.0 - 7000000.0;

        $this->assertEqualsWithDelta(82333000.0, $surplus, 0.005);
        $this->assertEqualsWithDelta(46673000.0, $accidentalAndEe, 0.005, 'RI-11 open question 5');
        $this->assertEqualsWithDelta(
            86883000.0,
            40000000.0 + $accidentalAndEe + $motorTradersExt,
            0.005,
            'the residual must decompose line by line or it cannot be defended'
        );
    }

    /**
     * The single clearest conflict. Schedule A caps Accidental Damage at 7,500,000
     * AND the Property/Fire class specific exclusion 15 excludes it above that
     * figure outright. Routing it to the Property class gives it a 10,000,000 line
     * plus surplus and cedes 47,000,000 against an express exclusion.
     */
    public function test_accidental_damage_is_ceded_against_an_express_exclusion(): void
    {
        $risk = null;
        foreach (self::POLICY as $r) {
            if ($r['group'] === 'ACCIDENTAL_DAMAGE_COM') {
                $risk = $r;
            }
        }
        $this->assertNotNull($risk);

        $scheduleA = $this->scheduleACeded((float) $risk['si'], $risk['class']);
        $this->assertEqualsWithDelta(5250000.0, $scheduleA, 0.005, '70% of the 7,500,000 limit');

        $instruction = (new RegulatoryCessionCalculator())->allocateRisk([
            'sum_insured' => $risk['si'],
            'premium'     => $risk['premium'],
            'mapping'     => $risk['mapping'],
        ]);

        $this->assertEqualsWithDelta(47000000.0, $instruction['ceded_si'], 0.005);
        $this->assertEqualsWithDelta(41750000.0, $instruction['ceded_si'] - $scheduleA, 0.005);

        // The excluded band: everything above 7,500,000 on a 120,050,000 risk.
        $this->assertEqualsWithDelta(
            112550000.0,
            $risk['si'] - self::SCHEDULE_A['Accidental Damage']['limit'],
            0.005,
            'sum insured sitting above the exclusion'
        );
    }

    /** Neither slip establishes a surplus, yet the restatement cedes through one. */
    public function test_the_surplus_layer_has_no_schedule_a_capacity_behind_it(): void
    {
        $totals = $this->instructionBasis()['totals'];

        $this->assertEqualsWithDelta(82333000.0, $totals['surplus_si'], 0.005);

        // Every Schedule A row states one quota share limit and nothing else, so
        // there is no column the 82,333,000 could be drawn against.
        foreach (self::SCHEDULE_A as $class => $spec) {
            $this->assertArrayNotHasKey('surplus', $spec, "$class states a surplus capacity");
        }

        // Open item 3 of RI-TRTY-WP-01: four lines on the net retention is
        // 12,000,000, not 40,000,000 on the gross first line.
        $this->assertSame(12000000.0, 4 * 3000000.0);
        $this->assertSame(40000000.0, 4 * 10000000.0);
    }

    // ----------------------------------------------------- treaty level sense check

    /**
     * Class capacity is not the only cap. Whatever basis is adopted, the most
     * recoverable in a single occurrence is the event limit — so 350,909,000 of
     * reported cession is not 350,909,000 of recoverable cover.
     */
    public function test_reported_cession_far_exceeds_the_event_limit_on_one_policy(): void
    {
        $ceded = $this->instructionBasis()['totals']['ceded_si'];

        $this->assertGreaterThan(self::GENERAL_EVENT_LIMIT, $ceded);
        $this->assertGreaterThan(self::MOTOR_EVENT_LIMIT, $ceded);
        $this->assertEqualsWithDelta(5.013, $ceded / self::GENERAL_EVENT_LIMIT, 0.001);

        // On the Schedule A basis a single policy stays inside the event limit.
        $scheduleA = 0.0;
        foreach (self::POLICY as $r) {
            $scheduleA += $this->scheduleACeded((float) $r['si'], $r['class']);
        }
        $this->assertLessThan(self::GENERAL_EVENT_LIMIT, $scheduleA);
    }

    // ------------------------------------------------------------- data integrity

    public function test_the_policy_fixture_agrees_with_the_reported_totals(): void
    {
        $si = $prem = 0.0;
        foreach (self::POLICY as $r) {
            $si   += (float) $r['si'];
            $prem += (float) $r['premium'];
        }

        $this->assertEqualsWithDelta(self::TOTAL_SI, $si, 0.005);
        $this->assertEqualsWithDelta(self::TOTAL_PREMIUM, $prem, 0.005);
    }

    /** Both bases must account for the whole sum insured, whatever they cede. */
    public function test_both_bases_account_for_the_entire_sum_insured(): void
    {
        $totals = $this->instructionBasis()['totals'];
        $this->assertEqualsWithDelta(
            self::TOTAL_SI,
            $totals['ceded_si'] + $totals['retained_si'],
            0.005,
            'instruction basis loses sum insured'
        );

        $ceded = 0.0;
        foreach (self::POLICY as $r) {
            $ceded += $this->scheduleACeded((float) $r['si'], $r['class']);
        }
        $this->assertEqualsWithDelta(
            self::TOTAL_SI,
            $ceded + (self::TOTAL_SI - $ceded),
            0.005,
            'Schedule A basis loses sum insured'
        );
    }
}
