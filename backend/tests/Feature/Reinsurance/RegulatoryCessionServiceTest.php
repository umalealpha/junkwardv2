<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Services\Reinsurance\RegulatoryCessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The wire between the policy and RegulatoryCessionCalculator.
 *
 * RegulatoryCessionCalculatorTest proves the arithmetic and
 * VtestPolicyConformanceTest proves it against Reinsurance's own workbook. This
 * proves the part that had never existed: that a policy is READ onto the
 * regulatory mapping basis correctly, at the right grain, from the right table.
 *
 * THE SOURCE IS policy_reinsurance_details, not the coverage rows. The first
 * version of this service read policy_coverage_detail.coverage_value and could
 * not be reconciled: motor sum insured never arrives that way — it comes through
 * the vehicle, and the legacy motor path reads prid.n_SumInsured — so the whole
 * class went missing, 38,000,000 on the test policy. The staged table has also
 * already decided which group owns each coverage, which the coverage rows have
 * not: one code sits in as many as six groups.
 *
 * ONE ROW PER LAYER. The staged table repeats the same sum insured once per
 * formula on the group, so it has to be counted once per (coverage detail, risk
 * address) or every layered class multiplies. MOTOR_COM on COMG2026213751 holds
 * 8 rows over 2 coverage details and sums to 152,000,000 against a real
 * 38,000,000 — exactly four layers.
 *
 * SAFETY: the same two hazards as the other Reinsurance feature tests — .env's
 * default connection is a live server, and the audit driver falls back to it via
 * mysql_system. setUp() forces in-memory sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/RegulatoryCessionServiceTest.php
 */
class RegulatoryCessionServiceTest extends TestCase
{
    private const ACTION_ID = 5001;

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

        // The live 2026/27 configuration, as apply_uniform_treaty_limit_2627.php
        // wrote it: one 10,000,000 treaty first line for every mapping.
        config([
            'reinsurance.params' => [
                'first_line' => 10000000.0, 'retention_leg' => 3000000.0,
                'quota_share_leg' => 7000000.0, 'surplus' => 40000000.0,
                'auto_fac' => 50000000.0, 'fac' => 50000000.0,
                'retention_pct' => 0.30, 'cession_pct' => 0.70,
                'ceding_commission_pct' => 0.375,
            ],
            'reinsurance.class_limits' => [
                'Motor' => 10000000.0, 'Transportation' => 10000000.0,
                'Miscellaneous' => 10000000.0, 'Guarantee' => 10000000.0,
            ],
            'reinsurance.group_limits'    => ['MOTOR_TRAILERS_COM' => 1500000.0],
            'reinsurance.retained_groups' => ['TRAVELINSURANCE_COM', 'TRAVELINSURANCE_DOM'],
        ]);

        $this->buildSchema();
    }

    private function service(): RegulatoryCessionService
    {
        return app(RegulatoryCessionService::class);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Reading the policy onto the mapping basis
    // ────────────────────────────────────────────────────────────────────

    public function test_it_reads_staged_rows_onto_their_regulatory_mapping(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $risks = $this->service()->risksFor(self::ACTION_ID);

        $this->assertCount(2, $risks);
        $this->assertSame(['Motor', 'Property'], collect($risks)->pluck('mapping')->sort()->values()->all());
    }

    public function test_it_takes_the_staged_sum_insured_and_premium(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);

        $risks = $this->service()->risksFor(self::ACTION_ID);

        $this->assertSame(12000000.0, $risks[0]['sum_insured']);
        $this->assertSame(60000.0, $risks[0]['premium']);
    }

    /**
     * An action that has never been computed has no staged rows and reads as
     * empty rather than as a confident zero. The legacy engine has nothing to
     * cede from either, so there is genuinely nothing to compare.
     */
    public function test_an_uncomputed_action_reads_as_empty(): void
    {
        $this->assertSame([], $this->service()->risksFor(self::ACTION_ID));
    }

    // ────────────────────────────────────────────────────────────────────
    //  One figure per coverage row, however many layers stage it
    // ────────────────────────────────────────────────────────────────────

    /**
     * THE MULTIPLICATION THE STAGED TABLE CARRIES. It holds a row per formula on
     * the group, repeating the same sum insured on each. MOTOR_COM on
     * COMG2026213751 holds 8 rows over 2 coverage details and sums to
     * 152,000,000 against a real 38,000,000 — four layers, four times the
     * exposure.
     */
    public function test_four_layers_on_one_coverage_count_once(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null, detailId: 11, layers: 4);

        $risks = $this->service()->risksFor(self::ACTION_ID);

        $this->assertCount(1, $risks);
        $this->assertSame(4000000.0, $risks[0]['sum_insured'], 'not 16,000,000');
        $this->assertSame(20000.0, $risks[0]['premium'], 'not 80,000');
    }

    /** Two coverage details on one group still add together. */
    public function test_two_coverage_rows_on_one_group_do_add(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 34000000, 14000000, riskAddress: null, detailId: 11, layers: 4);
        $this->stage('MOTOR_COM', 'Motor', 4000000, 40000, riskAddress: null, detailId: 12, layers: 4);

        $risks = $this->service()->risksFor(self::ACTION_ID);

        $this->assertCount(1, $risks, 'one group, one risk');
        $this->assertSame(38000000.0, $risks[0]['sum_insured'], 'the two coverage rows, each once');
    }

    // ────────────────────────────────────────────────────────────────────
    //  The grain the treaty tests on
    // ────────────────────────────────────────────────────────────────────

    /**
     * Two Property groups at one risk address share ONE layer stack. Confirmed
     * 24 August 2026. Allocating them separately would build two stacks and
     * invent capacity the treaty never gave.
     */
    public function test_two_property_groups_at_one_address_share_one_stack(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 8000000, 40000, riskAddress: 1, detailId: 21);
        $this->stage('ENGINEERING_AND_BI_COM', 'Property', 6000000, 30000, riskAddress: 1, detailId: 22);

        $a = $this->service()->allocate(self::ACTION_ID);

        $this->assertCount(1, $a['units'], 'one address, one stack');
        $this->assertSame(14000000.0, (float) $a['units'][0]['sum_insured']);
        $this->assertSame(3000000.0, (float) $a['units'][0]['net_retention_si']);
        $this->assertSame(7000000.0, (float) $a['units'][0]['quota_share_si']);
        $this->assertSame(4000000.0, (float) $a['units'][0]['surplus_si']);
    }

    public function test_two_addresses_earn_two_stacks(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 8000000, 40000, riskAddress: 1, detailId: 21);
        $this->stage('PROPERTYANDBI_COM', 'Property', 8000000, 40000, riskAddress: 2, detailId: 22);

        $a = $this->service()->allocate(self::ACTION_ID);

        $this->assertCount(2, $a['units'], 'two addresses, two stacks');

        // Each address is 8,000,000 — below the 10,000,000 first line — so each
        // takes a plain 30/70 rather than the flat retention leg.
        foreach ($a['units'] as $u) {
            $this->assertSame(2400000.0, (float) $u['net_retention_si']);
            $this->assertSame(5600000.0, (float) $u['quota_share_si']);
        }

        // Merged into one 16,000,000 stack these would have taken the flat legs
        // and pushed 6,000,000 into surplus.
        $this->assertSame(0.0, (float) $a['totals']['surplus_si'], 'the addresses were not merged');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Limits from configuration, not the calculator's own defaults
    // ────────────────────────────────────────────────────────────────────

    /**
     * The calculator's own ROUTES cap Motor at the Schedule A 5,000,000. The live
     * tables carry the uniform 10,000,000 confirmed on 26 August, and building on
     * the defaults would have moved live cession back to the basis that
     * understated it by 56,172,000.
     */
    public function test_the_class_limit_comes_from_configuration(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 34000000, 100000, riskAddress: null);

        $u = $this->service()->allocate(self::ACTION_ID)['units'][0];

        $this->assertSame(10000000.0, (float) $u['class_limit'], 'the live limit, not Schedule A');
        $this->assertSame(3000000.0, (float) $u['net_retention_si']);
        $this->assertSame(7000000.0, (float) $u['quota_share_si']);
        // Above the limit the excess cascades: Auto FAC, then FAC, then outside.
        $this->assertSame(24000000.0, (float) $u['auto_fac_si']);
    }

    /** The trailer carve-out survives the uniform limit. Schedule A: any one trailer. */
    public function test_the_trailer_group_keeps_its_narrower_limit(): void
    {
        $this->stage('MOTOR_TRAILERS_COM', 'Motor', 5000000, 10000, riskAddress: null);

        $u = $this->service()->allocate(self::ACTION_ID)['units'][0];

        $this->assertSame(1500000.0, (float) $u['class_limit']);
        $this->assertSame(450000.0, (float) $u['net_retention_si']);
        $this->assertSame(1050000.0, (float) $u['quota_share_si']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Unmapped, unplaced and held-out classes are reported, never skipped
    // ────────────────────────────────────────────────────────────────────

    public function test_an_unmapped_group_is_retained_and_raised(): void
    {
        $this->stage('SOMETHING_COM', '', 9000000, 45000, riskAddress: null);

        $a = $this->service()->allocate(self::ACTION_ID);

        $this->assertSame(0.0, (float) $a['units'][0]['ceded_si']);
        $this->assertSame(9000000.0, (float) $a['units'][0]['retained_si']);
        $this->assertCount(1, $a['exceptions']);
        $this->assertStringContainsString('no treaty route', $a['exceptions'][0]['exception']);
    }

    public function test_liability_is_retained_by_design(): void
    {
        $this->stage('PUBLICLIAB_COM', 'Liability', 5000000, 25000, riskAddress: null);

        $a = $this->service()->allocate(self::ACTION_ID);

        $this->assertSame(0.0, (float) $a['units'][0]['ceded_si']);
        $this->assertSame(5000000.0, (float) $a['units'][0]['retained_si']);
    }

    /**
     * Travel insurance is classed Miscellaneous and cedes nothing. Miscellaneous
     * cedes 30/70 within its class limit, so the class alone would have ceded 70%
     * of every travel risk.
     */
    public function test_travel_insurance_reports_as_miscellaneous_and_cedes_nothing(): void
    {
        $this->stage('TRAVELINSURANCE_COM', 'Miscellaneous', 800000, 8000, riskAddress: null);

        $u = $this->service()->allocate(self::ACTION_ID)['units'][0];

        $this->assertSame('Miscellaneous', $u['mapping'], 'it still reports under its class');
        $this->assertSame(0.0, (float) $u['ceded_si'], 'and cedes nothing');
        $this->assertSame(800000.0, (float) $u['net_retention_si']);
        $this->assertFalse($u['in_treaty']);
    }

    public function test_other_miscellaneous_groups_still_cede(): void
    {
        $this->stage('MISC_COM', 'Miscellaneous', 800000, 8000, riskAddress: null);

        $u = $this->service()->allocate(self::ACTION_ID)['units'][0];

        $this->assertSame(560000.0, (float) $u['ceded_si'], '70% of 800,000');
        $this->assertTrue($u['in_treaty']);
    }

    /** A group holding two classes cannot be routed, and is reported not guessed. */
    public function test_a_group_holding_two_classes_is_reported(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        DB::table('reinsurance_group_coverage')->insert([
            'id' => 999, 'group_id' => $this->groupId('MOTOR_COM'),
            'coverage_name' => 'ODDITY', 'regulatory_mapping' => 'Property',
        ]);

        $ambiguous = $this->service()->ambiguousCoverages(self::ACTION_ID);

        $this->assertCount(1, $ambiguous);
        $this->assertSame('MOTOR_COM', $ambiguous[0]['group']);
        $this->assertStringContainsString('Motor', $ambiguous[0]['classes']);
        $this->assertStringContainsString('Property', $ambiguous[0]['classes']);
    }

    public function test_a_group_with_one_class_is_not_reported(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $this->assertSame([], $this->service()->ambiguousCoverages(self::ACTION_ID));
    }

    // ────────────────────────────────────────────────────────────────────
    //  Ceding commission
    // ────────────────────────────────────────────────────────────────────

    /**
     * 37.5% of the ceded premium. Confirmed 31 August 2026: the 3 August slips
     * state 37.5%, and the slip governs where the Final Terms read "To be
     * advised".
     */
    public function test_ceding_commission_is_taken_on_the_ceded_premium(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $u = $this->service()->allocate(self::ACTION_ID)['units'][0];

        $this->assertSame(14000.0, (float) $u['ceded_premium']);
        $this->assertSame(5250.0, (float) $u['ceding_commission'], '37.5% of 14,000');
    }

    public function test_a_retained_class_earns_no_ceding_commission(): void
    {
        $this->stage('PUBLICLIAB_COM', 'Accident', 5000000, 25000, riskAddress: null);

        $u = $this->service()->allocate(self::ACTION_ID)['units'][0];

        $this->assertSame(0.0, (float) $u['ceded_premium']);
        $this->assertSame(0.0, (float) $u['ceding_commission']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  The comparison against the legacy figures
    // ──────────────────────────────────────── what a cutover would need

    /**
     * THE ENGINE FLAG DOES NOT DECIDE ANYTHING YET, and this pins that so nobody
     * reads the config key and assumes it does. The whole application takes its
     * cession from policy_reinsurance, which the legacy chain writes; switching
     * engines means producing rows of that shape, not changing a setting.
     */
    public function test_the_legacy_engine_is_still_the_live_one(): void
    {
        $this->assertSame('legacy', $this->service()->engine());
        $this->assertTrue($this->service()->legacyEngineIsLive());
    }

    /** An unrecognised value falls back to legacy rather than to nothing. */
    public function test_an_unknown_engine_falls_back_to_legacy(): void
    {
        config(['reinsurance.engine' => 'something_else']);

        $this->assertSame('legacy', $this->service()->engine());
    }

    public function test_the_flag_can_name_the_regulatory_engine(): void
    {
        config(['reinsurance.engine' => 'regulatory']);

        $this->assertSame('regulatory', $this->service()->engine());
        $this->assertFalse($this->service()->legacyEngineIsLive());
    }

    /**
     * The regulatory allocation, in the shape policy_reinsurance stores.
     *
     * This is the artefact Reinsurance compares before a cutover: the same
     * columns the legacy engine writes, so a movement can be agreed line by line.
     */
    public function test_it_expresses_the_allocation_as_policy_reinsurance_rows(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $rows = $this->service()->asPolicyReinsuranceRows(self::ACTION_ID);

        $this->assertCount(1, $rows);
        $this->assertSame('MOTOR_COM', $rows[0]['group_code']);
        $this->assertSame('Motor', $rows[0]['regulatory_class']);
        $this->assertSame(4000000.0, $rows[0]['totalSumInsured']);
        $this->assertSame(2800000.0, $rows[0]['treatySI'], '70% of 4,000,000');
        $this->assertSame(14000.0, $rows[0]['treatyPremium']);
        $this->assertSame(70.0, $rows[0]['treatyPercentage']);
    }

    /**
     * THE COLUMNS THAT CANNOT BE FILLED HONESTLY ARE LEFT NULL. There is no
     * formula behind a regulatory layer, so inventing one would put a figure on
     * the row that traces to nothing.
     */
    public function test_the_formula_columns_are_null_not_invented(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $row = $this->service()->asPolicyReinsuranceRows(self::ACTION_ID)[0];

        $this->assertNull($row['formula_id']);
        $this->assertNull($row['type_id']);
        $this->assertNull($row['UsedFormula']);
    }

    /**
     * The layers travel with the row even though policy_reinsurance has nowhere
     * to put them, so a difference against the legacy figure can be EXPLAINED
     * rather than only measured.
     */
    public function test_the_layers_travel_with_the_row(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 34000000, 100000, riskAddress: null);

        $row = $this->service()->asPolicyReinsuranceRows(self::ACTION_ID)[0];

        $this->assertSame(3000000.0, $row['layers']['net_retention']);
        $this->assertSame(7000000.0, $row['layers']['quota_share']);
        $this->assertSame(24000000.0, $row['layers']['auto_fac']);
        $this->assertSame(0.0, $row['layers']['surplus']);
    }

    /**
     * A layered unit can span several groups at one risk address, and then no
     * single group_id is true of it. Reported as the set rather than one of them
     * picked arbitrarily.
     */
    public function test_a_unit_spanning_two_groups_carries_no_single_group_id(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 8000000, 40000, riskAddress: 1, detailId: 31);
        $this->stage('ENGINEERING_AND_BI_COM', 'Property', 6000000, 30000, riskAddress: 1, detailId: 32);

        $rows = $this->service()->asPolicyReinsuranceRows(self::ACTION_ID);

        $this->assertCount(1, $rows, 'one address, one stack');
        $this->assertNull($rows[0]['group_id'], 'no single group is true of it');
        $this->assertCount(2, $rows[0]['groups']);
    }

    /** Producing the rows writes nothing. */
    public function test_producing_the_rows_writes_nothing(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $this->service()->asPolicyReinsuranceRows(self::ACTION_ID);

        $this->assertSame(0, DB::table('policy_reinsurance')->count());
    }

    // ────────────────────────────────────────────────────────────────────

    public function test_it_compares_against_what_the_legacy_engine_published(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        DB::table('policy_reinsurance')->insert([
            'action_id' => self::ACTION_ID, 'totalSumInsured' => '4,000,000',
            'treatySI'  => '2,800,000', 'treatyPremium' => '14,000',
        ]);

        $c = $this->service()->compare(self::ACTION_ID);

        $this->assertTrue($c['legacy_published']);

        // WHAT THE LEGACY CHAIN ALLOCATED, NOT WHAT IT CEDED. It sums treatySI
        // across every layer and every formula, so the figure is an allocation
        // and says so — this test used to call it ceded_si and assert a zero
        // delta against the regulatory cession, which is a comparison between
        // two different quantities that happened to agree on this one action.
        $this->assertSame(2800000.0, $c['legacy']['allocated_si']);
        $this->assertFalse($c['legacy']['is_cession']);

        $this->assertSame(2800000.0, $c['regulatory']['ceded_si']);

        // NO DELTA, DELIBERATELY. Subtracting an allocation from a cession
        // measures nothing, so both sides are reported and neither is netted.
        $this->assertNull($c['delta']);
    }

    /**
     * A zero delta against an action the legacy engine never published would read
     * as agreement. It is not, and the comparison says so.
     */
    public function test_it_says_when_the_legacy_engine_published_nothing(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $c = $this->service()->compare(self::ACTION_ID);

        $this->assertFalse($c['legacy_published']);
        $this->assertSame(0, $c['legacy']['rows']);
    }

    /** Nothing in this service writes. A report must not move a stored figure. */
    public function test_the_comparison_writes_nothing(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: null);

        $this->service()->compare(self::ACTION_ID);

        $this->assertSame(0, DB::table('policy_reinsurance')->count());
    }

    // ───────────────────────────────────────── harness

    private function groupId(string $groupCode): int
    {
        return (int) DB::table('reinsurance_group')->where('group_code', $groupCode)->value('id');
    }

    /**
     * Stage one coverage on a group, the way Compute does.
     *
     * $layers repeats the SAME sum insured across that many formula rows, which
     * is what the live table holds and what the read has to collapse.
     */
    private function stage(
        string $groupCode,
        string $mapping,
        float $sumInsured,
        float $premium,
        ?int $riskAddress,
        ?int $detailId = null,
        int $layers = 1
    ): void {
        static $n = 0;
        $n++;
        $detailId ??= 100 + $n;

        $groupId = DB::table('reinsurance_group')->where('group_code', $groupCode)->value('id');
        if (! $groupId) {
            $groupId = 500 + $n;
            DB::table('reinsurance_group')->insert(['id' => $groupId, 'group_code' => $groupCode]);
            DB::table('reinsurance_group_coverage')->insert([
                'id' => 600 + $n, 'group_id' => $groupId,
                'coverage_name' => $groupCode . '-CVG', 'regulatory_mapping' => $mapping,
            ]);
        }

        for ($i = 0; $i < $layers; $i++) {
            DB::table('policy_reinsurance_details')->insert([
                'action_id'            => self::ACTION_ID,
                'group_id'             => $groupId,
                'formula_id'           => 900 + $i,
                'risk_address_id'      => $riskAddress,
                'pocoverage_detail_id' => $detailId,
                'n_SumInsured'         => $sumInsured,
                'n_Premium'            => $premium,
            ]);
        }
    }

    /**
     * A GROUP THAT CARRIES ITS OWN MAPPING AND NO COVERAGE ROWS STILL ROUTES.
     *
     * reinsurance_group has a regulatory_mapping column of its own, and five
     * live groups have one with nothing behind it in reinsurance_group_coverage
     * — AVIATION_COM (Transportation), BODY_CORPORATE_COM (Liability),
     * MOTOR_PER_ACCIDENT (Motor) and both TRAVELINSURANCE groups. Reading only
     * the coverage rows saw nothing for those and retained them whole.
     *
     * None carries business today, so this fixes a trap rather than a loss —
     * and the trap is the quiet kind: 100% retained is a legitimate answer, so
     * the day Aviation is written nobody would have seen an error.
     */
    public function test_a_group_mapping_is_used_when_there_are_no_coverage_rows(): void
    {
        DB::table('reinsurance_group')->insert([
            'id' => 900, 'group_code' => 'AVIATION_COM', 'regulatory_mapping' => 'Transportation',
        ]);
        // Deliberately NO reinsurance_group_coverage row.
        DB::table('policy_reinsurance_details')->insert([
            'action_id' => self::ACTION_ID, 'group_id' => 900,
            'risk_address_id' => 3, 'pocoverage_detail_id' => 9001,
            'n_SumInsured' => 8000000, 'n_Premium' => 40000,
        ]);

        $risks = $this->service()->risksFor(self::ACTION_ID);

        $this->assertCount(1, $risks);
        $this->assertSame('Transportation', $risks[0]['mapping'],
            'the group row carries the mapping when no coverage row does');
    }

    /** The coverage rows still win where both exist. */
    public function test_a_coverage_mapping_beats_the_group_mapping(): void
    {
        DB::table('reinsurance_group')->insert([
            'id' => 901, 'group_code' => 'ODD_COM', 'regulatory_mapping' => 'Miscellaneous',
        ]);
        DB::table('reinsurance_group_coverage')->insert([
            'id' => 901, 'group_id' => 901,
            'coverage_name' => 'ODD-CVG', 'regulatory_mapping' => 'Property',
        ]);
        DB::table('policy_reinsurance_details')->insert([
            'action_id' => self::ACTION_ID, 'group_id' => 901,
            'risk_address_id' => 4, 'pocoverage_detail_id' => 9002,
            'n_SumInsured' => 5000000, 'n_Premium' => 25000,
        ]);

        $risks = $this->service()->risksFor(self::ACTION_ID);

        $this->assertSame('Property', $risks[0]['mapping']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Storing the regulatory allocation
    // ────────────────────────────────────────────────────────────────────

    /**
     * IT WRITES ONLY TO ITS OWN TABLE. This is the property the whole cutover
     * plan rests on: both bases stored side by side, legacy still deciding what
     * is reported, and a rollback that is just "stop reading the new table".
     */
    public function test_persisting_does_not_touch_policy_reinsurance(): void
    {
        DB::table('policy_reinsurance')->insert([
            'action_id' => self::ACTION_ID, 'totalSumInsured' => '99', 'treatySI' => '77',
        ]);
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);

        $this->service()->persist(self::ACTION_ID);

        $legacy = DB::table('policy_reinsurance')->where('action_id', self::ACTION_ID)->first();
        $this->assertSame('99', $legacy->totalSumInsured, 'legacy row must be untouched');
        $this->assertSame('77', $legacy->treatySI);
    }

    /** One row per layer that carries something, at the risk-address grain. */
    public function test_it_writes_one_row_per_non_nil_layer(): void
    {
        // 12,000,000 Property: 3m net, 7m quota share, 2m surplus. No FAC, no
        // outside-treaty, so three rows and not six.
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);

        $r = $this->service()->persist(self::ACTION_ID);

        $this->assertSame(3, $r['rows']);
        $this->assertSame(1, $r['units']);

        $layers = $this->service()->storedLayers(self::ACTION_ID);
        $this->assertSame(
            ['net_retention', 'quota_share', 'surplus'],
            collect(array_keys($layers))->sort()->values()->all()
        );
        $this->assertSame(3000000.0, $layers['net_retention']['sum_insured']);
        $this->assertSame(7000000.0, $layers['quota_share']['sum_insured']);
        $this->assertSame(2000000.0, $layers['surplus']['sum_insured']);
    }

    /** The layers must add back to the sum insured, or something is lost. */
    public function test_the_stored_layers_foot_to_the_sum_insured(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 250000000, 1375000, riskAddress: 1);
        $this->stage('MOTOR_COM', 'Motor', 38000000, 190000, riskAddress: null);

        $r = $this->service()->persist(self::ACTION_ID);

        $stored = array_sum(array_column($this->service()->storedLayers(self::ACTION_ID), 'sum_insured'));

        $this->assertEqualsWithDelta(288000000.0, $stored, 0.01);
        $this->assertEqualsWithDelta($r['sum_insured'], $stored, 0.01,
            'every pula of sum insured must land in exactly one layer');
    }

    /** Premium follows the sum insured at the policy's own rate. */
    public function test_premium_is_apportioned_across_the_layers(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);

        $this->service()->persist(self::ACTION_ID);
        $layers = $this->service()->storedLayers(self::ACTION_ID);

        // 60,000 on 12,000,000 is 0.5%, so the legs carry 15,000 / 35,000 / 10,000.
        $this->assertSame(15000.0, $layers['net_retention']['premium']);
        $this->assertSame(35000.0, $layers['quota_share']['premium']);
        $this->assertSame(10000.0, $layers['surplus']['premium']);
        $this->assertEqualsWithDelta(
            60000.0,
            array_sum(array_column($layers, 'premium')),
            0.01
        );
    }

    /**
     * RE-RUNNING REPLACES, IT DOES NOT APPEND. An action that held two
     * allocations would be reported twice and reconciled never.
     */
    public function test_persisting_twice_replaces_rather_than_appends(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);

        $first  = $this->service()->persist(self::ACTION_ID);
        $second = $this->service()->persist(self::ACTION_ID);

        $this->assertSame($first['rows'], $second['rows']);
        $this->assertSame(
            $first['rows'],
            DB::table('policy_reinsurance_regulatory')->where('action_id', self::ACTION_ID)->count()
        );
    }

    /** A re-run after the figures move stores the NEW allocation, not both. */
    public function test_a_rerun_reflects_changed_figures(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 12000000, 60000, riskAddress: 1);
        $this->service()->persist(self::ACTION_ID);

        DB::table('policy_reinsurance_details')->where('action_id', self::ACTION_ID)->delete();
        $this->stage('PROPERTYANDBI_COM', 'Property', 8000000, 40000, riskAddress: 1);
        $this->service()->persist(self::ACTION_ID);

        $layers = $this->service()->storedLayers(self::ACTION_ID);

        // 8,000,000 is under the 10,000,000 first line: 30/70, no surplus.
        $this->assertArrayNotHasKey('surplus', $layers);
        $this->assertSame(2400000.0, $layers['net_retention']['sum_insured']);
        $this->assertSame(5600000.0, $layers['quota_share']['sum_insured']);
    }

    /**
     * AUTO FAC AND FAC ARE NOT MARKED AS PLACED. Reinsurance's own work paper,
     * open item 8: the percentage is only reinsured if a slip has actually been
     * placed. Null says "not yet known", which is the truth; false would assert
     * it had been checked and true would assert cover that may not exist.
     */
    public function test_facultative_layers_are_stored_as_unplaced_unknown(): void
    {
        $this->stage('PROPERTYANDBI_COM', 'Property', 150000000, 750000, riskAddress: 1);

        $this->service()->persist(self::ACTION_ID);

        $rows = DB::table('policy_reinsurance_regulatory')
            ->where('action_id', self::ACTION_ID)
            ->pluck('is_placed', 'layer');

        $this->assertNull($rows['auto_fac']);
        $this->assertNull($rows['fac']);
        $this->assertTrue((bool) $rows['quota_share'], 'treaty capacity is placed by definition');
    }

    /** The exception on a unit is stored with it, not dropped on the way in. */
    public function test_an_exception_is_stored_against_the_rows(): void
    {
        $this->stage('TRAVELINSURANCE_COM', 'Miscellaneous', 5000000, 25000, riskAddress: 1);

        $this->service()->persist(self::ACTION_ID);

        $row = DB::table('policy_reinsurance_regulatory')
            ->where('action_id', self::ACTION_ID)
            ->first();

        $this->assertNotNull($row->exception);
        $this->assertStringContainsString('100% retained', $row->exception);
    }

    /**
     * TWO GROUPS, ONE CLASS, ONE ADDRESS. This is the case that broke the first
     * book-wide run: risksFor() groups by (risk address, GROUP, mapping), so
     * MOTOR_COM and MOTOR_TRADERS_COM_EXT at the same address are two units
     * that both carry the class Motor. The table was created unique on
     * (action, address, class, layer), which describes a grain the reader does
     * not produce, and four of forty-eight actions failed on it.
     */
    public function test_two_groups_of_one_class_at_one_address_both_persist(): void
    {
        $this->stage('MOTOR_COM', 'Motor', 4000000, 20000, riskAddress: 7);
        $this->stage('MOTOR_TRADERS_COM_EXT', 'Motor', 6000000, 30000, riskAddress: 7);

        $r = $this->service()->persist(self::ACTION_ID);

        $this->assertSame(2, $r['units'], 'two groups, so two units');
        $this->assertSame(4, $r['rows'], 'each splits 30/70 within the first line');

        $stored = DB::table('policy_reinsurance_regulatory')
            ->where('action_id', self::ACTION_ID)
            ->where('layer', 'quota_share')
            ->orderBy('group_code')
            ->pluck('sum_insured', 'group_code');

        $this->assertEqualsWithDelta(2800000.0, (float) $stored['MOTOR_COM'], 0.01);
        $this->assertEqualsWithDelta(4200000.0, (float) $stored['MOTOR_TRADERS_COM_EXT'], 0.01);
    }

    /** An action with nothing staged stores nothing, and says so. */
    public function test_an_action_with_no_risks_stores_nothing(): void
    {
        $r = $this->service()->persist(self::ACTION_ID);

        $this->assertSame(0, $r['rows']);
        $this->assertSame([], $this->service()->storedLayers(self::ACTION_ID));
    }

    private function buildSchema(): void
    {
        // The staged table the legacy engine cedes from. One row per layer.
        Schema::create('policy_reinsurance_details', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->unsignedBigInteger('formula_id')->nullable();
            $t->unsignedBigInteger('risk_address_id')->nullable();
            $t->unsignedBigInteger('pocoverage_detail_id')->nullable();
            $t->decimal('n_SumInsured', 20, 2)->default(0);
            $t->decimal('n_Premium', 20, 2)->default(0);
        });

        Schema::create('reinsurance_group', function ($t) {
            $t->id();
            $t->string('group_code')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            // The real table carries this and the read now falls back to it.
            $t->string('regulatory_mapping')->nullable();
        });

        Schema::create('reinsurance_group_coverage', function ($t) {
            $t->id();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->string('coverage_name')->nullable();
            $t->string('regulatory_mapping')->nullable();
        });

        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('term_id')->nullable();
            $t->unsignedBigInteger('transaction_id')->nullable();
            $t->unsignedBigInteger('treaty_id')->nullable();
            $t->string('totalSumInsured')->nullable();
            $t->string('treatySI')->nullable();
            $t->string('treatyPremium')->nullable();
            // THE FIXTURE HAS TO CARRY WHAT THE QUERIES READ. legacyTotals()
            // counts distinct formula_id and type_id and filters on deleted_at,
            // and none of the three were here — so the three tests that call it
            // errored with "no such column" rather than failing on a figure.
            // All nullable, as they are on the real table.
            $t->unsignedBigInteger('formula_id')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        // Mirrors 2026_09_01_120000_create_policy_reinsurance_regulatory_table.
        Schema::create('policy_reinsurance_regulatory', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id');
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('term_id')->nullable();
            $t->unsignedBigInteger('transaction_id')->nullable();
            $t->string('risk_address', 191)->nullable();
            $t->string('regulatory_class', 64);
            $t->string('layer', 32);
            $t->unsignedBigInteger('group_id')->nullable();
            $t->string('group_code', 191)->nullable();
            $t->unsignedBigInteger('treaty_id')->nullable();
            $t->decimal('sum_insured', 20, 2)->default(0);
            $t->decimal('premium', 20, 2)->default(0);
            $t->boolean('is_treaty_capacity')->default(true);
            $t->boolean('is_placed')->nullable();
            $t->string('route', 128)->nullable();
            $t->text('exception')->nullable();
            $t->string('basis_version', 32)->default('2026-27');
            $t->string('added_by', 191)->nullable();
            $t->timestamps();
            $t->softDeletes();

            // The real table carries this. Without it the schema here was
            // laxer than production and could not catch a grain error --
            // which is exactly what happened: four actions failed on the
            // first book-wide run against a constraint no test exercised.
            $t->unique(
                ['action_id', 'risk_address', 'group_code', 'regulatory_class', 'layer'],
                'pri_regulatory_grain_unique'
            );
        });
    }
}
