<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Services\Reinsurance\FacCoverageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CHARACTERISATION. What FacCoverageService::shortfallFor() does TODAY.
 *
 * This service had no tests at all and is live in UAT — Reinsurance are testing
 * the FAC register against it now. It also reads treatySI/treatyPremium straight
 * out of policy_reinsurance, which makes it one of the three sites that must
 * move onto CessionSource before the cession basis can be switched.
 *
 * MOSTLY IT ASSERTS WHAT IS, NOT WHAT SHOULD BE. That is the point of a
 * characterisation test: it stops a refactor changing behaviour by accident,
 * and one that improves things on the way past cannot tell you whether the move
 * itself was safe.
 *
 * TWO TESTS NOW GO FURTHER, and they were added AFTER the move, not during it.
 * Over-cession used to vanish into a nil shortfall and the policy reported as
 * fully covered. The floor was right — an over-ceded unit has no facultative
 * requirement and must not net off a real gap elsewhere — but nothing surfaced
 * it. It is now counted beside the shortfall, never added to it, and those two
 * tests pin the corrected behaviour rather than the old.
 *
 * THE GRAIN IS THE THING TO PROTECT. A risk unit is (group, coverage), and the
 * staged table repeats the same sum insured once per treaty layer, so risk_si is
 * MAX and treaty_si is SUM. Get that pair backwards and a four-layer coverage
 * either quadruples the exposure or quarters the cession.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/FacCoverageShortfallCharacterisationTest.php
 */
class FacCoverageShortfallCharacterisationTest extends TestCase
{
    private const POLICY_ID = 9001;
    private const ACTION_ID = 9101;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected in-memory sqlite, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        config(['audit.enabled' => false]);
        config(['fac.coverage.mandatory_si_threshold' => 50000000.0]);

        $this->buildSchema();
    }

    private function service(): FacCoverageService
    {
        return app(FacCoverageService::class);
    }

    /**
     * One staged cession row. The real table stores money as VARCHAR with
     * thousands separators, which is why the service casts and strips commas —
     * so the fixtures are strings with commas too, or the test proves nothing.
     */
    private function row(
        int $groupId,
        int $coverageId,
        string $totalSi,
        string $totalPrm,
        string $treatySi,
        string $treatyPrm
    ): void {
        DB::table('policy_reinsurance')->insert([
            'policy_id'        => self::POLICY_ID,
            'action_id'        => self::ACTION_ID,
            'group_id'         => $groupId,
            'coverage_id'      => $coverageId,
            'totalSumInsured'  => $totalSi,
            'totalPremium'     => $totalPrm,
            'treatySI'         => $treatySi,
            'treatyPremium'    => $treatyPrm,
        ]);
    }

    private function shortfall(): array
    {
        return $this->service()->shortfallFor(self::POLICY_ID, self::ACTION_ID);
    }

    // ───────────────────────────────────────────── the grain

    /**
     * A coverage running four layers is FOUR ROWS repeating the same sum
     * insured. risk_si takes MAX and treaty_si takes SUM, so the exposure is
     * counted once and the cession is counted per layer.
     *
     * 10,000,000 of risk with 3m + 7m ceded leaves nothing outside.
     */
    public function test_layers_of_one_coverage_count_the_risk_once_and_the_cession_per_layer(): void
    {
        $this->row(1, 100, '10,000,000.00', '50,000.00', '3,000,000.00', '15,000.00');
        $this->row(1, 100, '10,000,000.00', '50,000.00', '7,000,000.00', '35,000.00');

        $s = $this->shortfall();

        $this->assertSame(1, $s['rows'], 'two layers are one risk unit');
        $this->assertSame(0.0, $s['sumInsured']);
        $this->assertSame(0.0, $s['premium']);
        $this->assertSame(10000000.0, $s['byGroup'][0]['riskSi']);
        $this->assertSame(10000000.0, $s['byGroup'][0]['treatySi']);
    }

    /** Different coverages in one group are separate risk units. */
    public function test_coverages_within_a_group_are_separate_units(): void
    {
        $this->row(1, 100, '10,000,000.00', '50,000.00', '10,000,000.00', '50,000.00');
        $this->row(1, 200, '4,000,000.00', '20,000.00', '1,000,000.00', '5,000.00');

        $s = $this->shortfall();

        $this->assertSame(2, $s['rows']);
        $this->assertSame(3000000.0, $s['sumInsured'], 'only the second unit is short');
        $this->assertCount(1, $s['byGroup'], 'but they roll up to one group');
        $this->assertSame(14000000.0, $s['byGroup'][0]['riskSi']);
    }

    // ───────────────────────────────────────────── the shortfall itself

    public function test_the_shortfall_is_risk_less_what_the_treaty_took(): void
    {
        $this->row(1, 100, '100,000,000.00', '500,000.00', '10,000,000.00', '50,000.00');

        $s = $this->shortfall();

        $this->assertSame(90000000.0, $s['sumInsured']);
        $this->assertSame(450000.0, $s['premium']);
    }

    /**
     * A TREATY CANNOT ABSORB MORE THAN THE RISK, and where it appears to the
     * service floors the shortfall at nil rather than letting a negative net off
     * a genuine gap elsewhere on the policy.
     *
     * THE FLOOR IS RIGHT AND STAYS. What was wrong is that nothing reported
     * the over-cession: the policy showed as fully covered. The live case is
     * COMG2026213718, which cedes 820,544 against a sum insured of 410,272 —
     * the real figures are used here. It is now counted alongside the
     * shortfall, never added to it.
     */
    public function test_an_over_ceded_unit_floors_at_nil_and_does_not_offset(): void
    {
        $this->row(1, 100, '410,272.00', '2,000.00', '820,544.00', '4,000.00');
        $this->row(1, 200, '5,000,000.00', '25,000.00', '1,000,000.00', '5,000.00');

        $s = $this->shortfall();

        $this->assertSame(4000000.0, $s['sumInsured'],
            'the over-ceded unit contributes nil, not minus 410,272');
        $this->assertSame(20000.0, $s['premium']);

        // And it is now visible rather than absorbed.
        $this->assertSame(410272.0, $s['overCededSumInsured']);
        $this->assertSame(1, $s['overCededUnits']);
    }

    /**
     * A policy whose ONLY fault is over-cession must not read as clean. Before
     * this it reported nil outside the treaty and nothing else, which is the
     * most reassuring answer the check could give about its least trustworthy
     * input.
     */
    public function test_an_over_ceded_policy_no_longer_reads_as_fully_covered(): void
    {
        $this->row(1, 100, '410,272.00', '2,000.00', '820,544.00', '4,000.00');

        $s = $this->shortfall();

        $this->assertSame(0.0, $s['sumInsured'], 'still no facultative requirement');
        $this->assertSame(410272.0, $s['overCededSumInsured'], 'but the fault is reported');
        $this->assertSame(1, $s['overCededUnits']);
        $this->assertTrue($s['byGroup'][0]['overCeded']);
    }

    /** A policy that cedes sensibly reports no over-cession at all. */
    public function test_a_clean_policy_reports_no_over_cession(): void
    {
        $this->row(1, 100, '10,000,000.00', '50,000.00', '7,000,000.00', '35,000.00');

        $s = $this->shortfall();

        $this->assertSame(0.0, $s['overCededSumInsured']);
        $this->assertSame(0, $s['overCededUnits']);
        $this->assertFalse($s['byGroup'][0]['overCeded']);
    }

    // ───────────────────────────────────────────── the mandatory threshold

    /**
     * Placement is mandatory only ABOVE 50,000,000, and the test is on the risk
     * — not on the shortfall. A 60,000,000 risk short by 1,000,000 is mandatory;
     * a 40,000,000 risk short by 40,000,000 is not.
     */
    public function test_mandatory_counts_only_units_over_the_threshold(): void
    {
        $this->row(1, 100, '60,000,000.00', '300,000.00', '59,000,000.00', '295,000.00');
        $this->row(1, 200, '40,000,000.00', '200,000.00', '0.00', '0.00');

        $s = $this->shortfall();

        $this->assertSame(41000000.0, $s['sumInsured'], 'both are short');
        $this->assertSame(1000000.0, $s['mandatorySumInsured'], 'only the large risk is mandatory');
    }

    /** Exactly at the threshold is NOT above it. */
    public function test_the_threshold_is_exclusive(): void
    {
        $this->row(1, 100, '50,000,000.00', '250,000.00', '0.00', '0.00');

        $s = $this->shortfall();

        $this->assertSame(50000000.0, $s['sumInsured']);
        $this->assertSame(0.0, $s['mandatorySumInsured'], '50,000,000 exactly is not "in excess of"');
    }

    /** A group is flagged mandatory if ANY unit in it is over the threshold. */
    public function test_a_group_is_mandatory_if_any_unit_is(): void
    {
        $this->row(1, 100, '60,000,000.00', '300,000.00', '10,000,000.00', '50,000.00');
        $this->row(1, 200, '1,000,000.00', '5,000.00', '0.00', '0.00');

        $s = $this->shortfall();

        $this->assertTrue($s['byGroup'][0]['mandatory']);
    }

    // ───────────────────────────────────────────── shape and edges

    public function test_an_action_with_no_rows_reports_nil_rather_than_failing(): void
    {
        $s = $this->shortfall();

        $this->assertSame(0.0, $s['sumInsured']);
        $this->assertSame(0.0, $s['premium']);
        $this->assertSame(0, $s['rows']);
        $this->assertSame([], $s['byGroup']);
        $this->assertSame(self::ACTION_ID, $s['actionId']);
    }

    /** Soft-deleted cession rows are not cession. */
    public function test_deleted_rows_are_ignored(): void
    {
        $this->row(1, 100, '10,000,000.00', '50,000.00', '10,000,000.00', '50,000.00');
        DB::table('policy_reinsurance')->update(['deleted_at' => '2026-08-01 00:00:00']);

        $this->assertSame(0, $this->shortfall()['rows']);
    }

    /** Another action's rows belong to another action. */
    public function test_rows_from_a_different_action_are_excluded(): void
    {
        $this->row(1, 100, '10,000,000.00', '50,000.00', '1,000,000.00', '5,000.00');
        DB::table('policy_reinsurance')->insert([
            'policy_id' => self::POLICY_ID, 'action_id' => 9999, 'group_id' => 1,
            'coverage_id' => 100, 'totalSumInsured' => '999,000,000.00',
            'totalPremium' => '0.00', 'treatySI' => '0.00', 'treatyPremium' => '0.00',
        ]);

        $this->assertSame(9000000.0, $this->shortfall()['sumInsured']);
    }

    /**
     * A group with no reinsurance_group row still reports, under a made-up
     * label rather than vanishing.
     */
    public function test_an_unknown_group_is_labelled_not_dropped(): void
    {
        $this->row(77, 100, '5,000,000.00', '25,000.00', '0.00', '0.00');

        $s = $this->shortfall();

        $this->assertSame('group 77', $s['byGroup'][0]['groupCode']);
        $this->assertSame(5000000.0, $s['sumInsured']);
    }

    /** Commas and nulls in the varchar money columns read as numbers. */
    public function test_thousands_separators_and_nulls_are_handled(): void
    {
        DB::table('policy_reinsurance')->insert([
            'policy_id' => self::POLICY_ID, 'action_id' => self::ACTION_ID,
            'group_id' => 1, 'coverage_id' => 100,
            'totalSumInsured' => '1,234,567.89', 'totalPremium' => null,
            'treatySI' => null, 'treatyPremium' => null,
        ]);

        $s = $this->shortfall();

        $this->assertSame(1234567.89, $s['sumInsured']);
        $this->assertSame(0.0, $s['premium'], 'a null premium is nil, not an error');
    }

    private function buildSchema(): void
    {
        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->unsignedBigInteger('coverage_id')->nullable();
            $t->string('totalSumInsured')->nullable();
            $t->string('totalPremium')->nullable();
            $t->string('treatySI')->nullable();
            $t->string('treatyPremium')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('reinsurance_group', function ($t) {
            $t->id();
            $t->string('group_code')->nullable();
        });

        DB::table('reinsurance_group')->insert([
            ['id' => 1, 'group_code' => 'FIRE_COM'],
            ['id' => 2, 'group_code' => 'MOTOR_COM'],
        ]);
    }
}
