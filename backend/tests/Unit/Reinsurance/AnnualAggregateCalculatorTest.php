<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\AnnualAggregateCalculator;
use AlphaDirect\Services\Reinsurance\EventLimitCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The three annual aggregates and their erosion.
 *
 * The thing under test is not the arithmetic — capping a number at a number is
 * not hard. It is EROSION: that the pot is shared across the whole year, that it
 * is taken in date order, that it does not reset per occurrence the way an event
 * limit does, and that it empties.
 *
 *   20,000,000   General   SRCC, Zimbabwe                BR-CAP-16
 *    6,000,000   General   War / Civil War, GIT          BR-CAP-17
 *   50,000,000   Motor     Riot and Strike               BR-CAP-15
 */
class AnnualAggregateCalculatorTest extends TestCase
{
    private AnnualAggregateCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AnnualAggregateCalculator();
    }

    /** @param array<string,mixed> $extra */
    private function occurrence(string $at, float $ceded, array $extra = []): array
    {
        return [
            'first_loss_at'   => $at,
            'ceded_recovered' => $ceded,
        ] + $extra;
    }

    // ───────────────────────────────────────────── the limits, as the slips give them

    /**
     * @dataProvider theThree
     */
    public function test_the_three_limits(string $key, string $treaty, float $limit): void
    {
        $this->assertSame($limit, $this->calc->limitFor($key));
        $this->assertContains($key, $this->calc->keysFor($treaty));
    }

    public static function theThree(): array
    {
        return [
            'SRCC Zimbabwe'      => ['srcc_zimbabwe', 'general', 20000000.0],
            'War, GIT'           => ['war_goods_in_transit', 'general', 6000000.0],
            'Motor riot, strike' => ['motor_riot_strike', 'motor', 50000000.0],
        ];
    }

    public function test_general_and_motor_do_not_share_aggregates(): void
    {
        $this->assertSame(
            ['srcc_zimbabwe', 'war_goods_in_transit'],
            $this->calc->keysFor('general')
        );
        $this->assertSame(['motor_riot_strike'], $this->calc->keysFor('motor'));
        $this->assertSame([], $this->calc->keysFor('surplus'));
    }

    public function test_an_unknown_aggregate_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->limitFor('flood');
    }

    public function test_limits_can_be_overridden_but_only_by_name(): void
    {
        $c = new AnnualAggregateCalculator(['srcc_zimbabwe' => 25000000.0]);
        $this->assertSame(25000000.0, $c->limitFor('srcc_zimbabwe'));
        $this->assertSame(6000000.0, $c->limitFor('war_goods_in_transit'));

        $this->expectException(InvalidArgumentException::class);
        new AnnualAggregateCalculator(['hail' => 1.0]);
    }

    public function test_a_negative_limit_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AnnualAggregateCalculator(['srcc_zimbabwe' => -1.0]);
    }

    // ───────────────────────────────────────────── which occurrences are caught

    public function test_srcc_in_zimbabwe_is_caught(): void
    {
        $v = $this->calc->appliesTo('srcc_zimbabwe', [
            'peril'     => 'Riot',
            'territory' => 'Zimbabwe',
        ]);

        $this->assertTrue($v['applies']);
        $this->assertFalse($v['uncertain']);
    }

    public function test_the_same_peril_outside_zimbabwe_is_not(): void
    {
        $v = $this->calc->appliesTo('srcc_zimbabwe', [
            'peril'     => 'Riot',
            'territory' => 'Botswana',
        ]);

        $this->assertFalse($v['applies']);
    }

    public function test_a_different_peril_in_zimbabwe_is_not(): void
    {
        $v = $this->calc->appliesTo('srcc_zimbabwe', [
            'peril'     => 'Windstorm',
            'territory' => 'Zimbabwe',
        ]);

        $this->assertFalse($v['applies']);
    }

    /**
     * THE ONE THAT WOULD HAVE COST MONEY. On substring matching "war" is inside
     * "warehouse", so an ordinary warehouse fire would erode the 6,000,000 War
     * aggregate and leave nothing for an actual war loss.
     */
    public function test_a_warehouse_fire_is_not_a_war_loss(): void
    {
        $v = $this->calc->appliesTo('war_goods_in_transit', [
            'peril'    => 'Warehouse fire',
            'coverage' => 'Goods in Transit',
        ]);

        $this->assertFalse($v['applies'], '"war" must not match inside "warehouse"');
    }

    public function test_war_on_goods_in_transit_is_caught_in_any_territory(): void
    {
        foreach (['Botswana', 'Zimbabwe', 'South Africa'] as $where) {
            $v = $this->calc->appliesTo('war_goods_in_transit', [
                'peril'     => 'Civil War',
                'coverage'  => 'Goods in Transit',
                'territory' => $where,
            ]);

            $this->assertTrue($v['applies'], "expressly all territories, including {$where}");
            $this->assertFalse($v['uncertain'], 'territory is not a condition here');
        }
    }

    public function test_war_on_another_coverage_is_not_caught(): void
    {
        $v = $this->calc->appliesTo('war_goods_in_transit', [
            'peril'    => 'War',
            'coverage' => 'Fire and Allied Perils',
        ]);

        $this->assertFalse($v['applies']);
    }

    /**
     * Unknown territory on a matching peril is CAUGHT AND FLAGGED, not waved
     * through. Assuming the aggregate does not bite would recover more than the
     * treaty might allow and overstate the reinsurance asset.
     */
    public function test_an_unrecorded_territory_is_caught_and_flagged(): void
    {
        $v = $this->calc->appliesTo('srcc_zimbabwe', ['peril' => 'Civil commotion']);

        $this->assertTrue($v['applies']);
        $this->assertTrue($v['uncertain']);
        $this->assertStringContainsString('could not be tested', $v['reason']);
    }

    public function test_a_list_of_perils_reads_the_same_as_one(): void
    {
        $v = $this->calc->appliesTo('motor_riot_strike', [
            'perils' => ['Hail', 'Strikes'],
        ]);

        $this->assertTrue($v['applies']);
    }

    // ───────────────────────────────────────────── one occurrence at a time

    public function test_within_the_aggregate_the_whole_recovery_stands(): void
    {
        $o = $this->calc->apply(
            $this->occurrence('2026-09-01 08:00:00', 5000000.0,
                ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            'general'
        );

        $this->assertSame(5000000.0, $o['ceded_recovered']);
        $this->assertSame(0.0, $o['aggregate_declined']);
        $this->assertFalse($o['aggregate_exhausted']);
        $this->assertSame(5000000.0, $o['erosion_after']['srcc_zimbabwe']);
        $this->assertSame(15000000.0, $o['aggregates']['srcc_zimbabwe']['remaining']);
    }

    public function test_an_occurrence_over_the_aggregate_is_cut_to_it(): void
    {
        $o = $this->calc->apply(
            $this->occurrence('2026-09-01 08:00:00', 30000000.0,
                ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            'general'
        );

        $this->assertSame(20000000.0, $o['ceded_recovered']);
        $this->assertSame(10000000.0, $o['aggregate_declined']);
        $this->assertTrue($o['aggregate_exhausted']);
        $this->assertSame(0.0, $o['aggregates']['srcc_zimbabwe']['remaining']);
    }

    /** Erosion carried in reduces what is left, which is the whole point. */
    public function test_erosion_carried_in_reduces_the_recovery(): void
    {
        $o = $this->calc->apply(
            $this->occurrence('2026-11-01 08:00:00', 8000000.0,
                ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            'general',
            ['srcc_zimbabwe' => 17000000.0]
        );

        $this->assertSame(3000000.0, $o['ceded_recovered'], 'only 3,000,000 was left');
        $this->assertSame(5000000.0, $o['aggregate_declined']);
        $this->assertSame(20000000.0, $o['erosion_after']['srcc_zimbabwe']);
    }

    public function test_an_exhausted_aggregate_pays_nothing_further(): void
    {
        $o = $this->calc->apply(
            $this->occurrence('2027-01-01 08:00:00', 4000000.0,
                ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            'general',
            ['srcc_zimbabwe' => 20000000.0]
        );

        $this->assertSame(0.0, $o['ceded_recovered']);
        $this->assertSame(4000000.0, $o['aggregate_declined']);
        $this->assertTrue($o['aggregate_exhausted']);
    }

    /** An occurrence no aggregate catches passes through untouched. */
    public function test_an_uncaught_occurrence_is_untouched(): void
    {
        $o = $this->calc->apply(
            $this->occurrence('2026-09-01 08:00:00', 60000000.0,
                ['peril' => 'Windstorm', 'territory' => 'Botswana']),
            'general',
            ['srcc_zimbabwe' => 12000000.0]
        );

        $this->assertSame(60000000.0, $o['ceded_recovered']);
        $this->assertSame([], $o['aggregates']);
        $this->assertSame(12000000.0, $o['erosion_after']['srcc_zimbabwe'],
            'an unrelated peril must not erode the SRCC pot');
    }

    /**
     * The aggregate caps what the EVENT LIMIT already allowed. An occurrence
     * carrying both figures must erode by the recovery, not by what was sought.
     */
    public function test_the_aggregate_bites_after_the_event_limit(): void
    {
        $o = $this->calc->apply([
            'first_loss_at'   => '2026-09-01 08:00:00',
            'peril'           => 'Riot',
            'territory'       => 'Zimbabwe',
            'ceded_sought'    => 90000000.0,
            'ceded_recovered' => 70000000.0,   // event limit already applied
        ], 'general');

        $this->assertSame(70000000.0, $o['ceded_before_aggregate']);
        $this->assertSame(20000000.0, $o['ceded_recovered']);
        $this->assertSame(50000000.0, $o['aggregate_declined']);
    }

    /** With no event limit applied, what was sought is what the aggregate caps. */
    public function test_without_an_event_limit_the_sought_figure_is_used(): void
    {
        $o = $this->calc->apply([
            'first_loss_at' => '2026-09-01 08:00:00',
            'peril'         => 'Riot',
            'territory'     => 'Zimbabwe',
            'ceded_sought'  => 9000000.0,
        ], 'general');

        $this->assertSame(9000000.0, $o['ceded_before_aggregate']);
        $this->assertSame(9000000.0, $o['ceded_recovered']);
    }

    public function test_a_negative_recovery_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->apply(
            $this->occurrence('2026-09-01 08:00:00', -1.0, ['peril' => 'Riot']),
            'general'
        );
    }

    public function test_negative_erosion_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->apply(
            $this->occurrence('2026-09-01 08:00:00', 1000.0, ['peril' => 'Riot']),
            'general',
            ['srcc_zimbabwe' => -5.0]
        );
    }

    public function test_erosion_under_an_unknown_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->apply(
            $this->occurrence('2026-09-01 08:00:00', 1000.0, ['peril' => 'Riot']),
            'general',
            ['hailstorm' => 5.0]
        );
    }

    // ───────────────────────────────────────────── the year, walked

    /**
     * Three riots in Zimbabwe across the year. The pot is 20,000,000 and the
     * third one finds it nearly empty — which is the difference between an
     * aggregate and an event limit, where all three would have been paid.
     */
    public function test_the_pot_is_shared_across_the_year(): void
    {
        $r = $this->calc->runYear([
            $this->occurrence('2026-08-10 06:00:00', 9000000.0, ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            $this->occurrence('2026-12-02 06:00:00', 8000000.0, ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            $this->occurrence('2027-04-18 06:00:00', 7000000.0, ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
        ], 'general', 2026);

        $this->assertSame([9000000.0, 8000000.0, 3000000.0],
            array_column($r['occurrences'], 'ceded_recovered'));
        $this->assertSame(24000000.0, $r['totals']['ceded_before_aggregate']);
        $this->assertSame(20000000.0, $r['totals']['ceded_recovered']);
        $this->assertSame(4000000.0, $r['totals']['aggregate_declined']);
        $this->assertTrue($r['aggregates']['srcc_zimbabwe']['exhausted']);
        $this->assertSame(0.0, $r['aggregates']['srcc_zimbabwe']['remaining']);
        $this->assertSame('2026/27', $r['year']);
    }

    /**
     * ORDER DECIDES WHO IS PAID. Same two occurrences, opposite dates: whichever
     * comes first takes the headroom. An unsorted walk pays the wrong one.
     */
    public function test_order_decides_which_occurrence_is_paid(): void
    {
        $early = $this->occurrence('2026-08-01 06:00:00', 5000000.0,
            ['peril' => 'War', 'coverage' => 'Goods in Transit', 'reference' => 'A']);
        $late  = $this->occurrence('2027-03-01 06:00:00', 5000000.0,
            ['peril' => 'War', 'coverage' => 'Goods in Transit', 'reference' => 'B']);

        // Handed over out of order on purpose.
        $r = $this->calc->runYear([$late, $early], 'general', 2026);

        $this->assertSame(['A', 'B'], array_column($r['occurrences'], 'reference'),
            'sorted before it is walked');
        $this->assertSame([5000000.0, 1000000.0],
            array_column($r['occurrences'], 'ceded_recovered'));
    }

    /**
     * MOTOR RIOT IS THE ONE TO UNDERSTAND: the event limit and the annual
     * aggregate are both 50,000,000, so one riot at the full event limit
     * exhausts the year's riot cover. The second riot recovers nothing.
     */
    public function test_a_full_motor_riot_event_exhausts_the_year(): void
    {
        $events = new EventLimitCalculator();

        $first = $events->applyLimit([
            'first_loss_at' => '2026-08-05 12:00:00',
            'peril'         => 'Riot',
            'ceded_sought'  => 65000000.0,
        ], 'motor');

        $this->assertSame(50000000.0, $first['ceded_recovered'], 'the motor riot event limit');

        $second = $events->applyLimit([
            'first_loss_at' => '2027-02-11 12:00:00',
            'peril'         => 'Riot',
            'ceded_sought'  => 12000000.0,
        ], 'motor');

        $r = $this->calc->runYear([$first, $second], 'motor', 2026);

        $this->assertSame([50000000.0, 0.0], array_column($r['occurrences'], 'ceded_recovered'));
        $this->assertSame(12000000.0, $r['totals']['aggregate_declined']);
        $this->assertTrue($r['aggregates']['motor_riot_strike']['exhausted']);
    }

    /** A brought-forward balance opens the year part-eroded. */
    public function test_a_brought_forward_balance_is_honoured(): void
    {
        $r = $this->calc->runYear(
            [$this->occurrence('2026-09-09 06:00:00', 4000000.0,
                ['peril' => 'Civil War', 'coverage' => 'GIT'])],
            'general',
            2026,
            ['war_goods_in_transit' => 5000000.0]
        );

        $this->assertSame(1000000.0, $r['occurrences'][0]['ceded_recovered']);
        $this->assertSame(5000000.0, $r['aggregates']['war_goods_in_transit']['opening']);
        $this->assertSame(1000000.0, $r['aggregates']['war_goods_in_transit']['eroded']);
        $this->assertSame(6000000.0, $r['aggregates']['war_goods_in_transit']['closing']);
    }

    /** The year resets: the same losses in 2027/28 find a full pot. */
    public function test_the_next_year_starts_clean(): void
    {
        $loss = fn (string $at) => $this->occurrence($at, 15000000.0,
            ['peril' => 'Riot', 'territory' => 'Zimbabwe']);

        $a = $this->calc->runYear([$loss('2027-05-01 06:00:00')], 'general', 2026);
        $b = $this->calc->runYear([$loss('2027-08-01 06:00:00')], 'general', 2027);

        $this->assertSame(15000000.0, $a['occurrences'][0]['ceded_recovered']);
        $this->assertSame(15000000.0, $b['occurrences'][0]['ceded_recovered']);
        $this->assertSame('2027/28', $b['year']);
    }

    /**
     * @dataProvider outsideTheYear
     */
    public function test_a_loss_outside_the_year_is_refused(string $at): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside the 2026/27 treaty year');

        $this->calc->runYear(
            [$this->occurrence($at, 1000.0, ['peril' => 'Riot', 'territory' => 'Zimbabwe'])],
            'general',
            2026
        );
    }

    public static function outsideTheYear(): array
    {
        return [
            'the day before inception' => ['2026-06-30 23:59:59'],
            'the day after expiry'     => ['2027-07-01 00:00:00'],
            'a year early'             => ['2025-10-01 09:00:00'],
        ];
    }

    public function test_the_first_and_last_day_of_the_year_are_inside_it(): void
    {
        $r = $this->calc->runYear([
            $this->occurrence('2026-07-01 00:00:00', 1000.0, ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            $this->occurrence('2027-06-30 23:59:59', 2000.0, ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
        ], 'general', 2026);

        $this->assertSame(3000.0, $r['totals']['ceded_recovered']);
    }

    public function test_an_undated_occurrence_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no first_loss_at or occurred_at');

        $this->calc->runYear([['peril' => 'Riot', 'ceded_recovered' => 1.0]], 'general', 2026);
    }

    public function test_an_unreadable_date_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->runYear(
            [$this->occurrence('not a date', 1.0, ['peril' => 'Riot'])],
            'general',
            2026
        );
    }

    public function test_an_empty_year_reports_full_aggregates(): void
    {
        $r = $this->calc->runYear([], 'general', 2026);

        $this->assertSame(0, $r['totals']['occurrences']);
        $this->assertSame(20000000.0, $r['aggregates']['srcc_zimbabwe']['remaining']);
        $this->assertSame(6000000.0, $r['aggregates']['war_goods_in_transit']['remaining']);
        $this->assertFalse($r['aggregates']['srcc_zimbabwe']['exhausted']);
    }

    /** Two aggregates on one treaty erode independently. */
    public function test_the_two_general_aggregates_are_separate_pots(): void
    {
        $r = $this->calc->runYear([
            $this->occurrence('2026-08-01 06:00:00', 20000000.0,
                ['peril' => 'Riot', 'territory' => 'Zimbabwe']),
            $this->occurrence('2026-09-01 06:00:00', 4000000.0,
                ['peril' => 'War', 'coverage' => 'Goods in Transit']),
        ], 'general', 2026);

        $this->assertSame([20000000.0, 4000000.0],
            array_column($r['occurrences'], 'ceded_recovered'));
        $this->assertTrue($r['aggregates']['srcc_zimbabwe']['exhausted']);
        $this->assertFalse($r['aggregates']['war_goods_in_transit']['exhausted']);
        $this->assertSame(2000000.0, $r['aggregates']['war_goods_in_transit']['remaining']);
    }

    // ───────────────────────────────────────────── losses in, aggregate out

    /**
     * THE WHOLE PIPELINE, on raw losses.
     *
     * Losses → hours clause → occurrences → event limit → annual aggregate.
     * This is the test that says the aggregate is not a toy: it proves the
     * territory recorded against a loss survives the grouping and reaches the
     * aggregate, which is exactly what was missing before buildOccurrence
     * started carrying it.
     *
     * Two Zimbabwe riots 100 hours apart, so the 72-hour strike window makes
     * them two occurrences, not one. 14,000,000 and 9,000,000 against a
     * 20,000,000 pot: the first is paid in full, the second finds 6,000,000.
     */
    public function test_losses_run_all_the_way_through_to_the_aggregate(): void
    {
        $events = new EventLimitCalculator();

        $losses = [
            ['occurred_at' => '2026-08-03 09:00:00', 'peril' => 'Riot', 'ceded' => 14000000.0,
             'locality' => 'Harare', 'territory' => 'Zimbabwe', 'reference' => 'CLM-1'],
            ['occurred_at' => '2026-08-07 13:00:00', 'peril' => 'Riot', 'ceded' => 9000000.0,
             'locality' => 'Harare', 'territory' => 'Zimbabwe', 'reference' => 'CLM-2'],
        ];

        $assessed = $events->assess($losses, 'general');
        $this->assertCount(2, $assessed['occurrences'], '100 hours apart, so two occurrences');

        $r = $this->calc->runYear($assessed['occurrences'], 'general', 2026);

        $this->assertSame([14000000.0, 6000000.0],
            array_column($r['occurrences'], 'ceded_recovered'));
        $this->assertSame(3000000.0, $r['totals']['aggregate_declined']);
        $this->assertTrue($r['aggregates']['srcc_zimbabwe']['exhausted']);

        foreach ($r['occurrences'] as $o) {
            $this->assertFalse($o['aggregate_uncertain'],
                'the territory was recorded, so nothing should be uncertain');
        }
    }

    /**
     * The same two losses in Botswana erode nothing. The aggregate is Zimbabwe
     * only, and the event limit alone pays both in full.
     */
    public function test_the_same_losses_outside_zimbabwe_erode_nothing(): void
    {
        $events = new EventLimitCalculator();

        $losses = [
            ['occurred_at' => '2026-08-03 09:00:00', 'peril' => 'Riot', 'ceded' => 14000000.0,
             'locality' => 'Gaborone', 'territory' => 'Botswana'],
            ['occurred_at' => '2026-08-07 13:00:00', 'peril' => 'Riot', 'ceded' => 9000000.0,
             'locality' => 'Gaborone', 'territory' => 'Botswana'],
        ];

        $r = $this->calc->runYear(
            $events->assess($losses, 'general')['occurrences'],
            'general',
            2026
        );

        $this->assertSame(23000000.0, $r['totals']['ceded_recovered']);
        $this->assertSame(0.0, $r['totals']['aggregate_declined']);
        $this->assertSame(20000000.0, $r['aggregates']['srcc_zimbabwe']['remaining']);
    }

    /**
     * A loss with no territory recorded still runs, still erodes, and still
     * carries the flag through the hours clause.
     */
    public function test_a_loss_with_no_territory_still_flags_through_the_pipeline(): void
    {
        $events = new EventLimitCalculator();

        $r = $this->calc->runYear(
            $events->assess([
                ['occurred_at' => '2026-08-03 09:00:00', 'peril' => 'Riot',
                 'ceded' => 4000000.0, 'locality' => 'Harare'],
            ], 'general')['occurrences'],
            'general',
            2026
        );

        $this->assertSame(4000000.0, $r['occurrences'][0]['ceded_recovered']);
        $this->assertTrue($r['occurrences'][0]['aggregate_uncertain']);
        $this->assertSame(16000000.0, $r['aggregates']['srcc_zimbabwe']['remaining']);
    }

    /** Uncertainty survives the walk, so it can be chased rather than lost. */
    public function test_an_uncertain_occurrence_is_flagged_on_the_year(): void
    {
        $r = $this->calc->runYear([
            $this->occurrence('2026-08-01 06:00:00', 1000000.0, ['peril' => 'Riot']),
        ], 'general', 2026);

        $this->assertTrue($r['occurrences'][0]['aggregate_uncertain']);
        $this->assertNotNull($r['occurrences'][0]['aggregates']['srcc_zimbabwe']['exception']);
    }
}
