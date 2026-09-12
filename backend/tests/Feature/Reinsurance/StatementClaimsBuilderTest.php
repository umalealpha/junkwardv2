<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementClaimsBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Claims paid, salvages and recoveries — RI-18 step 3, BR-ACC-06.
 *
 * THE JOIN IS WHAT THESE PROTECT. claim_reserves_coverages.coverage_id and
 * policy_reinsurance.coverage_id are both called coverage_id and are different
 * identifiers — on the real book, matching them returns 10 rows out of 16,940,
 * which reads as "no claims on these policies" rather than as a broken join. The
 * claims column is a policy_coverage_detail.id, and so is
 * policy_reinsurance_details.pocoverage_detail_id. Joining those gives 1,904
 * matches. A refactor that reverts to the obvious join would silently zero the
 * claims side of every statement, so it is pinned here.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 */
class StatementClaimsBuilderTest extends TestCase
{
    /** The connection in force before setUp() switched it, restored in tearDown(). */
    private string $connectionBefore = 'mysql';

    /**
     * PUT THE CONNECTION BACK.
     *
     * setUp() switches the DEFAULT connection to in-memory sqlite, and that is
     * global state rather than something scoped to this file. Left switched,
     * every test running after it in the same process resolves against a
     * database holding none of its tables — which is how FacArithmeticTest
     * passed alone at 18 of 18 and errored inside the full suite, on an FX
     * lookup that had nothing to read.
     */
    protected function tearDown(): void
    {
        DB::purge('sqlite');
        DB::setDefaultConnection($this->connectionBefore);

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionBefore = (string) config('database.default');

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
        // These builders read the regulatory table directly and refuse on any
        // other basis, so the tests must say which basis they are exercising.
        config(['reinsurance.engine' => 'regulatory']);
        $this->buildSchema();
    }

    private function builder(): StatementClaimsBuilder
    {
        return app(StatementClaimsBuilder::class);
    }

    /**
     * A ceded unit: an action's allocation at one address, and the staged detail
     * row that ties a coverage detail to it.
     */
    private function unit(
        int $actionId,
        int $coverageDetailId,
        string $class,
        float $sumInsured,
        float $ceded,
        ?int $riskAddress = 1,
        ?int $groupId = null,
        string $groupCode = 'FIRE_COM'
    ): void {
        // The group has to exist for the join, and its code is what decides the
        // treaty — so each distinct code gets its own row rather than sharing one.
        $groupId ??= $this->groupIdFor($groupCode);

        DB::table('policy_reinsurance_details')->insert([
            'action_id'             => $actionId,
            'group_id'              => $groupId,
            'risk_address_id'       => $riskAddress,
            'pocoverage_detail_id'  => $coverageDetailId,
        ]);

        // The retained remainder, so the rate is ceded / sum insured.
        DB::table('policy_reinsurance_regulatory')->insert([
            ['action_id' => $actionId, 'risk_address' => $riskAddress, 'group_code' => $groupCode,
             'regulatory_class' => $class, 'layer' => 'quota_share',
             'sum_insured' => $ceded, 'premium' => 0, 'deleted_at' => null],
            ['action_id' => $actionId, 'risk_address' => $riskAddress, 'group_code' => $groupCode,
             'regulatory_class' => $class, 'layer' => 'net_retention',
             'sum_insured' => $sumInsured - $ceded, 'premium' => 0, 'deleted_at' => null],
        ]);
    }

    /** A row on the cash loss register — BR-ACC-08. */
    private function cashLoss(
        int $claimId,
        float $received,
        ?string $receivedOn,
        float $demanded = 0,
        string $demandedOn = '2026-08-15',
        string $treaty = 'general',
        int $year = 2026,
        ?int $accountedYear = null,
        ?int $accountedQuarter = null
    ): void {
        DB::table('treaty_cash_loss_recoveries')->insert([
            'treaty'            => $treaty,
            'underwriting_year' => $year,
            'claim_id'          => $claimId,
            'demanded_on'       => $demandedOn,
            'amount_demanded'   => $demanded ?: $received,
            'received_on'       => $receivedOn,
            'amount_received'   => $received,
            'accounted_year'    => $accountedYear,
            'accounted_quarter' => $accountedQuarter,
        ]);
    }

    /** The reinsurance_group row for a code, created once and reused. */
    private function groupIdFor(string $groupCode): int
    {
        $existing = DB::table('reinsurance_group')->where('group_code', $groupCode)->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('reinsurance_group')->insertGetId(['group_code' => $groupCode]);
    }

    private function claim(
        int $coverageDetailId,
        string $date,
        float $paid,
        float $salvage = 0,
        float $subrogation = 0,
        int $voided = 0,
        int $claimId = 1
    ): void {
        // The claim id is a parameter because the cash loss limit is tested PER
        // CLAIM: a fixture that put every payment on claim 1 could not tell
        // aggregation within a claim apart from summing unrelated ones, and the
        // second would manufacture breaches out of small claims.
        $reserveId = DB::table('claim_reserves')->insertGetId(['claim_id' => $claimId, 'date' => $date]);

        DB::table('claim_reserves_coverages')->insert([
            'reserve_id'          => $reserveId,
            'coverage_id'         => $coverageDetailId,
            'payment_amt'         => $paid,
            'salvage_payment'     => $salvage,
            'subrogation_payment' => $subrogation,
            'reserve_amt'         => 0,
            'is_payment_voided'   => $voided,
        ]);
    }

    // ───────────────────────────────────────────── the ungrouped allocation

    /**
     * AN UNGROUPED REGULATORY ROW IS NOT A BROKEN ONE, AND WAS BEING DROPPED.
     *
     * Where a regulatory class aggregates several groups the allocation carries
     * no group_code — Property at one risk address is PROPERTYANDBI_COM,
     * ACCIDENTAL_DAMAGE_COM and ELECTRONIC_EQ_AND_BI_COM together, and no single
     * code describes it. The join demanded one, so the row was dropped in
     * silence, taking 49,810,404.11 of ceded sum insured off every statement.
     *
     * It now falls back to the group's own regulatory_mapping.
     */
    public function test_an_allocation_with_no_group_code_still_reaches_the_statement(): void
    {
        $groupId = $this->groupIdFor('PROPERTYANDBI_COM');
        DB::table('reinsurance_group')->where('id', $groupId)
            ->update(['regulatory_mapping' => 'Property']);

        DB::table('policy_reinsurance_details')->insert([
            'action_id' => 1, 'group_id' => $groupId,
            'risk_address_id' => 1, 'pocoverage_detail_id' => 5001,
        ]);

        // The allocation names a class and NO group, as an aggregate must.
        DB::table('policy_reinsurance_regulatory')->insert([
            ['action_id' => 1, 'risk_address' => 1, 'group_code' => null,
             'regulatory_class' => 'Property', 'layer' => 'quota_share',
             'sum_insured' => 7_000_000, 'premium' => 0, 'deleted_at' => null],
            ['action_id' => 1, 'risk_address' => 1, 'group_code' => null,
             'regulatory_class' => 'Property', 'layer' => 'net_retention',
             'sum_insured' => 3_000_000, 'premium' => 0, 'deleted_at' => null],
        ]);

        $this->claim(5001, '2026-08-10', paid: 100_000);

        $c = $this->builder()->claimsFor('general', 2026, 1);

        $this->assertSame(1, $c['rows'], 'the ungrouped allocation must not be dropped');
        $this->assertSame(70_000.0, $c['ceded_net'], '70% of the loss');
    }

    /** A grouped row still matches on its code, and only on its own. */
    public function test_a_grouped_allocation_does_not_match_by_mapping(): void
    {
        $fire = $this->groupIdFor('FIRE_COM');
        DB::table('reinsurance_group')->where('id', $fire)
            ->update(['regulatory_mapping' => 'Property']);

        // Names a DIFFERENT group, so this detail row must not match it even
        // though the mapping would.
        $this->unit(1, 5001, 'Property', 10_000_000, 7_000_000,
            groupId: $fire, groupCode: 'PROPERTYANDBI_COM');
        $this->claim(5001, '2026-08-10', paid: 100_000);

        $this->assertSame(
            0,
            $this->builder()->claimsFor('general', 2026, 1)['rows'],
            'a named group code must be matched exactly, not by its mapping'
        );
    }

    /**
     * ONE PAYMENT IS ONE PAYMENT, however many detail rows describe the cover.
     *
     * policy_reinsurance_details holds a row per formula and layer, so a single
     * coverage detail appears several times on the same action, address and
     * group — coverage 56308 on the real book carries four. Joined raw, one
     * claim payment was counted once per row: 31 joined rows for 25 payments,
     * and a gross of 839,923.98 where the truth is 825,160.41.
     *
     * The duplication predates the mapping fallback; the strict join multiplied
     * harder still, at 1.33x against 1.24x.
     */
    public function test_a_payment_is_counted_once_however_many_detail_rows_exist(): void
    {
        $groupId = $this->groupIdFor('PROPERTYANDBI_COM');

        // Four detail rows, identical on every column the join uses.
        for ($i = 0; $i < 4; $i++) {
            DB::table('policy_reinsurance_details')->insert([
                'action_id' => 1, 'group_id' => $groupId,
                'risk_address_id' => 1, 'pocoverage_detail_id' => 5001,
            ]);
        }

        DB::table('policy_reinsurance_regulatory')->insert([
            ['action_id' => 1, 'risk_address' => 1, 'group_code' => 'PROPERTYANDBI_COM',
             'regulatory_class' => 'Property', 'layer' => 'quota_share',
             'sum_insured' => 7_000_000, 'premium' => 0, 'deleted_at' => null],
            ['action_id' => 1, 'risk_address' => 1, 'group_code' => 'PROPERTYANDBI_COM',
             'regulatory_class' => 'Property', 'layer' => 'net_retention',
             'sum_insured' => 3_000_000, 'premium' => 0, 'deleted_at' => null],
        ]);

        $this->claim(5001, '2026-08-10', paid: 100_000);

        $c = $this->builder()->claimsFor('general', 2026, 1);

        $this->assertSame(1, $c['rows'], 'four detail rows, one payment');
        $this->assertSame(100_000.0, $c['gross_paid'], 'not 400,000');
        $this->assertSame(70_000.0, $c['ceded_net']);
    }

    // ───────────────────────────────────────────── the basis

    /**
     * A STATEMENT MUST NOT MIX TWO CESSION BASES, AND IT DID.
     *
     * StatementPremiumBuilder reads through CessionSource and so follows
     * reinsurance.engine. This builder queries policy_reinsurance_regulatory
     * directly and, before this guard, did so whatever the configuration said.
     * On the real book that produced an account with premium drawn from the
     * whole legacy set — 50 actions — and claims drawn from the 9 actions staged
     * under the regulatory mapping. It balanced to 213,740.82 and the balance
     * meant nothing, because the two sides were different populations.
     *
     * The account FOOTED throughout, which is the lesson: footing proves the
     * arithmetic and says nothing about the inputs.
     */
    public function test_claims_refuse_to_build_on_the_legacy_basis(): void
    {
        config(['reinsurance.engine' => 'legacy']);

        $this->unit(actionId: 1, coverageDetailId: 5001, class: 'Property',
            sumInsured: 10000000, ceded: 7000000);
        $this->claim(5001, '2026-08-15', 100000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Claims cannot be built on the legacy basis');

        $this->builder()->claimsFor('general', 2026, 1);
    }

    /** The same guard covers the unmatched count and the cash loss scan. */
    public function test_every_claims_entry_point_is_guarded(): void
    {
        config(['reinsurance.engine' => 'legacy']);

        foreach ([
            fn () => $this->builder()->unmatchedFor('general', 2026, 1),
            fn () => $this->builder()->cashLossCandidatesFor('general', 2026, 1),
        ] as $i => $call) {
            try {
                $call();
                $this->fail("entry point {$i} produced figures on the legacy basis");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('legacy basis', $e->getMessage());
            }
        }
    }

    // ───────────────────────────────────────────── the join

    /**
     * THE JOIN THAT MATTERS. The claim's coverage_id is a coverage DETAIL id and
     * meets the cession through pocoverage_detail_id. Nothing matches on
     * policy_reinsurance.coverage_id, and a test that passed either way would
     * protect nothing.
     */
    public function test_a_claim_reaches_its_cession_through_the_coverage_detail(): void
    {
        $this->unit(actionId: 1, coverageDetailId: 5001, class: 'Property',
            sumInsured: 10000000, ceded: 7000000);
        $this->claim(5001, '2026-08-15', 100000);

        $c = $this->builder()->claimsFor('general', 2026, 1);

        $this->assertSame(1, $c['rows']);
        $this->assertSame(100000.0, $c['gross_paid']);
        $this->assertSame(70000.0, $c['ceded_net'], '70% ceded, so 70% of the loss');
    }

    /** A claim whose coverage detail reaches no cession contributes nothing. */
    public function test_an_unmatched_claim_contributes_nothing_and_is_counted(): void
    {
        $this->claim(9999, '2026-08-15', 100000);

        $this->assertSame(0, $this->builder()->claimsFor('general', 2026, 1)['rows']);
        $this->assertSame(1, $this->builder()->unmatchedFor('general', 2026, 1));
    }

    // ───────────────────────────────────────────── the rate

    /**
     * EACH UNIT CEDES AT ITS OWN RATE. Two coverages on one policy can cede
     * differently, and a policy-level rate would move money between them.
     */
    public function test_each_unit_cedes_at_its_own_rate(): void
    {
        $this->unit(1, 5001, 'Property', 10000000, 7000000, riskAddress: 1);
        $this->unit(1, 5002, 'Property', 10000000, 3000000, riskAddress: 2);
        $this->claim(5001, '2026-08-15', 100000);
        $this->claim(5002, '2026-08-15', 100000);

        // 70% of one and 30% of the other, not 50% of both.
        $this->assertSame(100000.0, $this->builder()->claimsFor('general', 2026, 1)['ceded_net']);
    }

    /** A wholly retained class cedes nothing, which is a real answer. */
    public function test_a_retained_class_cedes_no_part_of_a_loss(): void
    {
        $this->unit(1, 5001, 'Accident', 1000000, 0);
        $this->claim(5001, '2026-08-15', 50000);

        $c = $this->builder()->claimsFor('general', 2026, 1);

        $this->assertSame(50000.0, $c['gross_paid']);
        $this->assertSame(0.0, $c['ceded_net']);
    }

    // ───────────────────────────────────────────── netting and exclusions

    /** BR-ACC-06: claims paid LESS salvages and recoveries. */
    public function test_salvages_and_recoveries_come_off_the_claim(): void
    {
        $this->unit(1, 5001, 'Property', 1000000, 1000000);   // 100% ceded, easy arithmetic
        $this->claim(5001, '2026-08-15', 100000, salvage: 20000, subrogation: 5000);

        $this->assertSame(75000.0, $this->builder()->claimsFor('general', 2026, 1)['ceded_net']);
    }

    /** A voided payment never happened. */
    public function test_a_voided_payment_is_excluded(): void
    {
        $this->unit(1, 5001, 'Property', 1000000, 1000000);
        $this->claim(5001, '2026-08-15', 100000, voided: 1);

        $this->assertSame(0, $this->builder()->claimsFor('general', 2026, 1)['rows']);
    }

    /** The quarter is the transaction date on the reserve, not the claim date. */
    public function test_only_movement_inside_the_quarter_counts(): void
    {
        $this->unit(1, 5001, 'Property', 1000000, 1000000);
        $this->claim(5001, '2026-10-01', 100000);

        $this->assertSame(0.0, $this->builder()->claimsFor('general', 2026, 1)['ceded_net']);
        $this->assertSame(100000.0, $this->builder()->claimsFor('general', 2026, 2)['ceded_net']);
    }

    // ───────────────────────────────────────────── the written lines

    /**
     * Claims are NEGATIVE — money coming back. Salvages and recoveries are
     * written positive but excluded from the balance, because they are already
     * inside the net claim figure; counting them again would relieve the
     * reinsurer twice.
     */
    public function test_the_lines_are_signed_and_the_memoranda_stay_out_of_the_balance(): void
    {
        $this->unit(1, 5001, 'Property', 1000000, 1000000);
        $this->claim(5001, '2026-08-15', 100000, salvage: 20000);

        $s = $this->statement();
        $this->builder()->writeItems($s);

        $this->assertSame(-80000.0, (float) $s->items()
            ->where('item_type', TreatyStatementItem::CLAIMS_PAID)->sum('amount'));
        $this->assertSame(20000.0, (float) $s->items()
            ->where('item_type', TreatyStatementItem::SALVAGES)->sum('amount'));

        // The balance takes the claim once, not the claim and the salvage.
        $this->assertSame(-80000.0, round((float) $s->items()->settling()->sum('amount'), 2));
    }

    public function test_rebuilding_replaces_rather_than_appends(): void
    {
        $this->unit(1, 5001, 'Property', 1000000, 1000000);
        $this->claim(5001, '2026-08-15', 100000);

        $s = $this->statement();
        $this->builder()->writeItems($s);
        $first = $s->items()->count();
        $this->builder()->writeItems($s);

        $this->assertSame($first, $s->items()->count());
    }

    /**
     * A STATEMENT CARRIES ITS OWN TREATY'S BUSINESS ONLY, and until step 4 this
     * builder returned every class regardless of the treaty asked for — the
     * argument picked a commission rate and nothing else. A Motor reinsurer
     * would have been shown Property claims.
     */
    public function test_each_treaty_sees_only_its_own_groups(): void
    {
        $this->unit(1, 501, 'Property', 10_000_000, 7_000_000, groupCode: 'PROPERTYANDBI_COM');
        $this->unit(2, 502, 'Motor', 10_000_000, 7_000_000, riskAddress: 2, groupCode: 'MOTOR_COM');
        $this->claim(501, '2026-08-10', paid: 100_000);
        $this->claim(502, '2026-08-10', paid: 50_000);

        $general = $this->builder()->claimsFor('general', 2026, 1);
        $motor   = $this->builder()->claimsFor('motor', 2026, 1);

        $this->assertSame(['Property'], array_keys($general['by_class']));
        $this->assertSame(['Motor'], array_keys($motor['by_class']));
        $this->assertSame(70_000.0, $general['ceded_net']);
        $this->assertSame(35_000.0, $motor['ceded_net']);
    }

    /**
     * THE SPLIT IS BY GROUP, NOT BY CLASS, and this is the case that separates
     * them. MOTOR_TRADERS_COM_INT is a Motor-treaty group under RI-05, and on
     * this book its regulatory class is Miscellaneous rather than Motor. A split
     * on the class sends it to General; a split on the group sends it to Motor,
     * which is where the slip puts it.
     */
    public function test_a_motor_group_follows_its_treaty_not_its_class(): void
    {
        $this->unit(1, 501, 'Miscellaneous', 10_000_000, 7_000_000,
            groupCode: 'MOTOR_TRADERS_COM_INT');
        $this->claim(501, '2026-08-10', paid: 100_000);

        $this->assertSame([], array_keys($this->builder()->claimsFor('general', 2026, 1)['by_class']));
        $this->assertSame(
            ['Miscellaneous'],
            array_keys($this->builder()->claimsFor('motor', 2026, 1)['by_class']),
            'reported under its own class, but on the Motor statement'
        );
    }

    /**
     * TWO GROUPS SHARING A CLASS ARE ADDED, NOT OVERWRITTEN. The query groups by
     * class and group both, so they arrive as separate rows — an assignment
     * rather than an accumulation kept the last and dropped the other, losing a
     * whole group's claims from the statement without changing its shape.
     */
    public function test_two_groups_in_one_class_are_added_together(): void
    {
        $this->unit(1, 501, 'Motor', 10_000_000, 7_000_000, groupCode: 'MOTOR_COM');
        $this->unit(2, 502, 'Motor', 10_000_000, 7_000_000, riskAddress: 2,
            groupCode: 'MOTOR_TRAILERS_COM');
        $this->claim(501, '2026-08-10', paid: 100_000);
        $this->claim(502, '2026-08-10', paid: 40_000);

        $m = $this->builder()->claimsFor('motor', 2026, 1);

        $this->assertSame(140_000.0, $m['by_class']['Motor']['gross_paid']);
        $this->assertSame(98_000.0, $m['ceded_net'], '70% of both, not of one');
    }

    /**
     * An unrecognised group is not lost — it falls to General, the residual.
     *
     * The group here is named and joins on its code; what is unrecognised is
     * the code itself, which is in neither treaty's list. That is the treaty
     * SPLIT, and it is a different question from whether the row joins at all —
     * this test used to conflate the two by leaving both codes blank, which
     * matched only because '' equals ''.
     */
    public function test_an_unknown_group_falls_to_general_rather_than_vanishing(): void
    {
        $this->unit(1, 501, 'Property', 10_000_000, 7_000_000, groupCode: 'SOMETHING_NEW_COM');
        $this->claim(501, '2026-08-10', paid: 100_000);

        $this->assertSame(70_000.0, $this->builder()->claimsFor('general', 2026, 1)['ceded_net']);
        $this->assertSame(0.0, $this->builder()->claimsFor('motor', 2026, 1)['ceded_net']);
    }

    /**
     * A GROUP WITH NO REGULATORY MAPPING CANNOT BE TIED TO AN AGGREGATED
     * ALLOCATION, and is therefore counted rather than quietly dropped.
     *
     * An ungrouped allocation is matched by the group's regulatory_mapping. A
     * group carrying none belongs to no regulatory class, so there is nothing to
     * match it on — which is a real gap in the group's own configuration, not in
     * the claim.
     */
    public function test_a_group_with_no_mapping_cannot_join_an_aggregated_allocation(): void
    {
        $groupId = $this->groupIdFor('NO_MAPPING_COM');   // regulatory_mapping stays null

        DB::table('policy_reinsurance_details')->insert([
            'action_id' => 1, 'group_id' => $groupId,
            'risk_address_id' => 1, 'pocoverage_detail_id' => 5001,
        ]);
        DB::table('policy_reinsurance_regulatory')->insert([
            ['action_id' => 1, 'risk_address' => 1, 'group_code' => null,
             'regulatory_class' => 'Property', 'layer' => 'quota_share',
             'sum_insured' => 7_000_000, 'premium' => 0, 'deleted_at' => null],
        ]);
        $this->claim(5001, '2026-08-10', paid: 100_000);

        $this->assertSame(0, $this->builder()->claimsFor('general', 2026, 1)['rows']);
        $this->assertSame(
            1,
            $this->builder()->unmatchedFor('general', 2026, 1),
            'it must be counted, not silently absent'
        );
    }

    // ───────────────────────────── cash loss RECOVERIES, BR-ACC-08

    /**
     * CASH ALREADY SENT COMES OFF THE ACCOUNT, OR THE REINSURER PAYS TWICE.
     *
     * A cash loss is money demanded and paid ahead of the quarterly account.
     * When the quarter is made up the claim appears in claims paid at its full
     * ceded share, so the cash has to be deducted — and without the deduction
     * the account still foots.
     */
    public function test_cash_received_ahead_of_the_account_is_deducted(): void
    {
        $this->cashLoss(claimId: 1, received: 40_000, receivedOn: '2026-08-20');

        $r = $this->builder()->cashLossRecoveriesFor('general', 2026, 1);

        $this->assertSame(40_000.0, $r['total']);
        $this->assertSame(1, $r['rows']);
    }

    /**
     * A DEMAND NOBODY PAID IS A DEBT, NOT A RECOVERY. Deducting it would
     * relieve reinsurers of money they never sent.
     */
    public function test_a_demand_that_was_never_paid_is_not_deducted(): void
    {
        $this->cashLoss(claimId: 1, received: 0, receivedOn: null, demanded: 40_000);

        $this->assertSame(0.0, $this->builder()->cashLossRecoveriesFor('general', 2026, 1)['total']);
    }

    /**
     * IT FOLLOWS THE MONEY, NOT THE DEMAND. A demand made in Q1 and paid in Q2
     * belongs to Q2 — received_on decides, not demanded_on.
     */
    public function test_a_recovery_falls_in_the_quarter_the_money_arrived(): void
    {
        $this->cashLoss(claimId: 1, received: 40_000, receivedOn: '2026-11-20',
            demandedOn: '2026-09-20');

        $this->assertSame(0.0, $this->builder()->cashLossRecoveriesFor('general', 2026, 1)['total']);
        $this->assertSame(40_000.0, $this->builder()->cashLossRecoveriesFor('general', 2026, 2)['total']);
    }

    /**
     * DEDUCTED ONCE. A recovery already taken onto another quarter's account is
     * skipped and counted, because a second deduction is indistinguishable from
     * the first on the face of a statement.
     */
    public function test_a_recovery_already_accounted_elsewhere_is_skipped(): void
    {
        $this->cashLoss(claimId: 1, received: 40_000, receivedOn: '2026-08-20',
            accountedYear: 2025, accountedQuarter: 4);

        $r = $this->builder()->cashLossRecoveriesFor('general', 2026, 1);

        $this->assertSame(0.0, $r['total']);
        $this->assertSame(1, $r['skipped'], 'skipped, and said so');
    }

    /** The written line is positive, so it reduces what comes back. */
    public function test_the_recovery_line_reduces_the_balance(): void
    {
        $this->unit(1, 5001, 'Property', 10_000_000, 7_000_000, groupCode: 'PROPERTYANDBI_COM');
        $this->claim(5001, '2026-08-10', paid: 100_000);
        $this->cashLoss(claimId: 1, received: 40_000, receivedOn: '2026-08-20');

        $s = $this->statement();
        $this->builder()->writeItems($s);

        $claims = (float) $s->items()->where('item_type', TreatyStatementItem::CLAIMS_PAID)->sum('amount');
        $cash   = (float) $s->items()->where('item_type', TreatyStatementItem::CASH_LOSS_RECOVERY)->sum('amount');

        $this->assertSame(-70_000.0, $claims, 'the full ceded loss');
        $this->assertSame(40_000.0, $cash, 'cash already sent, positive');
        $this->assertSame(-30_000.0, round($claims + $cash, 2), 'only 30,000 still owed');
    }

    /** Rebuilding a quarter deducts the same cash once, not twice. */
    public function test_rebuilding_does_not_deduct_the_cash_twice(): void
    {
        $this->unit(1, 5001, 'Property', 10_000_000, 7_000_000, groupCode: 'PROPERTYANDBI_COM');
        $this->claim(5001, '2026-08-10', paid: 100_000);
        $this->cashLoss(claimId: 1, received: 40_000, receivedOn: '2026-08-20');

        $s = $this->statement();
        $this->builder()->writeItems($s);
        $this->builder()->writeItems($s);

        $this->assertSame(
            40_000.0,
            (float) $s->items()->where('item_type', TreatyStatementItem::CASH_LOSS_RECOVERY)->sum('amount')
        );
    }

    // ───────────────────────────── cash loss, BR-CLM-05/06 and BR-ACC-08

    /**
     * GENERAL IS TESTED ON THE GROSS CLAIM. BR-CLM-05 sets the limit at 500,000
     * "for 100% of the treaty", so a 600,000 claim breaches it even though only
     * 420,000 of it falls to reinsurers.
     */
    public function test_a_general_cash_loss_is_measured_on_the_whole_claim(): void
    {
        config(['reinsurance.terms.general.cash_loss_limit' => 500_000.0]);

        $this->unit(1, 501, 'Property', 10_000_000, 7_000_000, groupCode: 'PROPERTYANDBI_COM');
        $this->claim(501, '2026-08-10', paid: 600_000);

        $c = $this->builder()->cashLossCandidatesFor('general', 2026, 1);

        $this->assertCount(1, $c);
        $this->assertSame(600_000.0, $c[0]['gross_paid']);
        $this->assertSame(100_000.0, $c[0]['excess']);
        $this->assertSame('100% of the treaty', $c[0]['basis']);
    }

    /**
     * MOTOR IS TESTED ON THE CEDED PORTION. BR-CLM-06 sets 250,000 "for the
     * ceded portion", so a 400,000 claim ceding 70% breaches at 280,000 — and
     * the same claim measured gross would breach on either basis, while a
     * 300,000 claim ceding 70% is 210,000 and breaches on neither.
     */
    public function test_a_motor_cash_loss_is_measured_on_the_ceded_portion(): void
    {
        config(['reinsurance.terms.motor.cash_loss_limit' => 250_000.0]);

        $this->unit(1, 501, 'Motor', 10_000_000, 7_000_000, groupCode: 'MOTOR_COM');
        $this->claim(501, '2026-08-10', paid: 400_000);

        $c = $this->builder()->cashLossCandidatesFor('motor', 2026, 1);

        $this->assertCount(1, $c);
        $this->assertSame(280_000.0, $c[0]['ceded_paid'], '70% of 400,000');
        $this->assertSame(30_000.0, $c[0]['excess']);
        $this->assertSame('ceded portion', $c[0]['basis']);
    }

    /**
     * THE BASIS DECIDES THE ANSWER, not just the number. A Motor claim of
     * 300,000 ceding 70% is 210,000 to reinsurers and breaches nothing — where
     * testing it gross against 250,000 would raise a demand we are not entitled
     * to make.
     */
    public function test_a_motor_claim_under_the_ceded_limit_is_not_a_candidate(): void
    {
        config(['reinsurance.terms.motor.cash_loss_limit' => 250_000.0]);

        $this->unit(1, 501, 'Motor', 10_000_000, 7_000_000, groupCode: 'MOTOR_COM');
        $this->claim(501, '2026-08-10', paid: 300_000);

        $this->assertSame([], $this->builder()->cashLossCandidatesFor('motor', 2026, 1));
    }

    /** Payments on one claim aggregate before the limit is tested. */
    public function test_payments_on_one_claim_are_added_before_testing_the_limit(): void
    {
        config(['reinsurance.terms.general.cash_loss_limit' => 500_000.0]);

        $this->unit(1, 501, 'Property', 10_000_000, 10_000_000, groupCode: 'PROPERTYANDBI_COM');
        $this->claim(501, '2026-08-10', paid: 300_000);
        $this->claim(501, '2026-09-10', paid: 300_000);

        $c = $this->builder()->cashLossCandidatesFor('general', 2026, 1);

        $this->assertCount(1, $c, 'two payments, one claim, one breach');
        $this->assertSame(600_000.0, $c[0]['gross_paid']);
    }

    /**
     * TWO SEPARATE CLAIMS ARE NOT ONE BREACH. Each is 300,000 against a 500,000
     * limit and neither entitles us to demand anything; summing them across
     * claims would invent a cash loss out of two ordinary ones.
     */
    public function test_two_different_claims_are_not_summed_into_a_breach(): void
    {
        config(['reinsurance.terms.general.cash_loss_limit' => 500_000.0]);

        $this->unit(1, 501, 'Property', 10_000_000, 10_000_000, groupCode: 'PROPERTYANDBI_COM');
        $this->claim(501, '2026-08-10', paid: 300_000, claimId: 1);
        $this->claim(501, '2026-09-10', paid: 300_000, claimId: 2);

        $this->assertSame([], $this->builder()->cashLossCandidatesFor('general', 2026, 1));
    }

    /** A treaty with no configured limit refuses rather than passing everything. */
    public function test_a_treaty_with_no_cash_loss_limit_refuses(): void
    {
        config(['reinsurance.terms.general.cash_loss_limit' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No cash loss limit');

        $this->builder()->cashLossCandidatesFor('general', 2026, 1);
    }

    private function statement(): TreatyStatement
    {
        $p = TreatyStatement::periodFor(2026, 1);

        return TreatyStatement::create([
            'treaty' => 'general', 'underwriting_year' => 2026, 'quarter' => 1,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'status' => TreatyStatement::STATUS_DRAFT,
        ]);
    }

    private function buildSchema(): void
    {
        Schema::create('claim_reserves', function ($t) {
            $t->id();
            $t->unsignedBigInteger('claim_id')->nullable();
            $t->date('date')->nullable();
        });

        Schema::create('claim_reserves_coverages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('reserve_id')->nullable();
            $t->unsignedBigInteger('coverage_id')->nullable();
            $t->decimal('payment_amt', 20, 2)->default(0);
            $t->decimal('salvage_payment', 20, 2)->default(0);
            $t->decimal('subrogation_payment', 20, 2)->default(0);
            $t->decimal('reserve_amt', 20, 2)->default(0);
            $t->integer('is_payment_voided')->nullable();
        });

        Schema::create('policy_reinsurance_details', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->unsignedBigInteger('risk_address_id')->nullable();
            $t->unsignedBigInteger('pocoverage_detail_id')->nullable();
        });

        Schema::create('policy_reinsurance_regulatory', function ($t) {
            $t->id();
            $t->unsignedBigInteger('action_id');
            $t->string('risk_address', 191)->nullable();
            $t->string('group_code', 191)->nullable();
            $t->string('regulatory_class', 64);
            $t->string('layer', 32);
            $t->decimal('sum_insured', 20, 2)->default(0);
            $t->decimal('premium', 20, 2)->default(0);
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_cash_loss_recoveries', function ($t) {
            $t->id();
            $t->string('treaty', 32);
            $t->unsignedSmallInteger('underwriting_year');
            $t->unsignedBigInteger('claim_id');
            $t->string('reinsurer', 191)->nullable();
            $t->date('demanded_on');
            $t->decimal('amount_demanded', 20, 2)->default(0);
            $t->date('received_on')->nullable();
            $t->decimal('amount_received', 20, 2)->default(0);
            $t->string('annexure_a_reference', 191)->nullable();
            $t->unsignedTinyInteger('accounted_quarter')->nullable();
            $t->unsignedSmallInteger('accounted_year')->nullable();
            $t->text('note')->nullable();
            $t->string('added_by', 191)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('reinsurance_group', function ($t) {
            $t->id();
            $t->string('group_code')->nullable();
            // The class a group rolls up to. An ungrouped regulatory row matches
            // on THIS, because an aggregated allocation has no group of its own.
            $t->string('regulatory_mapping')->nullable();
        });
        DB::table('reinsurance_group')->insert([['id' => 1, 'group_code' => 'FIRE_COM']]);

        Schema::create('treaty_statements', function ($t) {
            $t->id();
            $t->string('treaty', 32);
            $t->unsignedSmallInteger('underwriting_year');
            $t->unsignedTinyInteger('quarter');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('render_due');
            $t->date('confirm_due')->nullable();
            $t->string('status', 32)->default('draft');
            $t->timestamp('rendered_at')->nullable();
            $t->string('rendered_by', 191)->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->string('confirmed_by', 191)->nullable();
            $t->string('cession_basis', 16)->default('legacy');
            $t->text('note')->nullable();
            $t->string('added_by', 191)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_statement_items', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_id');
            $t->string('item_type', 32);
            $t->string('regulatory_class', 64)->nullable();
            $t->unsignedSmallInteger('period_year')->nullable();
            $t->string('period_axis', 24)->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->decimal('basis_amount', 20, 2)->nullable();
            $t->decimal('rate', 12, 8)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
