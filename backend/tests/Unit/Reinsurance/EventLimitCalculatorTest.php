<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Services\Reinsurance\EventLimitCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The event limit and the hours clause.
 *
 * Two things are being proved here and they are not the same thing. The LIMIT is
 * arithmetic: cap the ceded recovery at 70,000,000. The HOURS CLAUSE decides what
 * one occurrence is, and that is where the money moves — group two losses into
 * one occurrence and the cap bites once; separate them and the treaty pays twice.
 *
 * Figures from the signed slips: 70,000,000 per occurrence on both treaties,
 * 50,000,000 for Motor riot and strike, 120,000,000 on the surplus.
 */
class EventLimitCalculatorTest extends TestCase
{
    private EventLimitCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new EventLimitCalculator();
    }

    // ───────────────────────────────────────── the window, by peril

    /**
     * @dataProvider perilWindows
     */
    public function test_the_hours_clause_runs_by_peril(string $peril, int $hours): void
    {
        $this->assertSame($hours, $this->calc->hoursFor($peril));
    }

    public static function perilWindows(): array
    {
        return [
            'windstorm'          => ['Windstorm', 72],
            'storm'              => ['Storm damage', 72],
            'earthquake'         => ['Earthquake', 72],
            'strike'             => ['Strike', 72],
            'riot'               => ['Riot and civil commotion', 72],
            'SRCC'               => ['SRCC', 72],
            'fire'               => ['Fire', 75],
            'fire, mixed case'   => ['Fire and Allied Perils', 75],
            // Note 6 names the fire group in full: explosion, conflagration,
            // firestorms, bush fires "and any other fires". All 75, not 168.
            'explosion'          => ['Explosion', 75],
            'conflagration'      => ['Conflagration', 75],
            'bush fire'          => ['Bush fire', 75],
            'malicious damage'   => ['Malicious damage', 72],
            'civil commotion'    => ['Civil commotion', 72],
            'theft, the default' => ['Theft', 168],
            'flood, the default' => ['Flood', 168],
        ];
    }

    // ───────────────────────────────────────── corrections from Note 6

    /**
     * MOTOR HAS NO FIRE CATEGORY. Note 6: Motor carries the same first four
     * categories "with no 75-hour fire category", so fire on Motor runs the
     * 168-hour default. Applying the General window to Motor would merge
     * occurrences the Motor slip keeps apart, and under-recover.
     */
    public function test_fire_runs_the_default_window_on_motor(): void
    {
        $this->assertSame(75, $this->calc->hoursFor('Fire', 'general'));
        $this->assertSame(168, $this->calc->hoursFor('Fire', 'motor'));
    }

    /** And with no fire category, Motor fire carries no place condition either. */
    public function test_motor_fire_carries_no_place_condition(): void
    {
        $this->assertSame('radius', $this->calc->localityConditionFor('Fire', 'general'));
        $this->assertNull($this->calc->localityConditionFor('Fire', 'motor'));
    }

    /**
     * THE STRIKE GROUP IS BOUNDED BY PLACE AS WELL AS TIME — "within the limits
     * of one city, town or village", Note 6. Two riots inside 72 hours in
     * different towns are two occurrences, and grouping them on the clock alone
     * would cap two events under one limit.
     */
    public function test_riots_in_different_towns_are_two_occurrences(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Riot', 'ceded' => 1000, 'locality' => 'Gaborone'],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Riot', 'ceded' => 1000, 'locality' => 'Francistown'],
        ]);

        $this->assertCount(2, $o);
        $this->assertSame('locality', $o[0]['place_clause']);
    }

    public function test_riots_in_one_town_inside_the_window_are_one_occurrence(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Riot', 'ceded' => 1000, 'locality' => 'Gaborone'],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Riot', 'ceded' => 1000, 'locality' => 'gaborone'],
        ]);

        $this->assertCount(1, $o);
    }

    /** Unknown locality is flagged, not assumed — as with fire's radius. */
    public function test_a_riot_with_no_locality_is_flagged(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Riot', 'ceded' => 1000],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Riot', 'ceded' => 1000],
        ]);

        $this->assertCount(1, $o);
        $this->assertTrue($o[0]['place_untested']);
        $this->assertStringContainsString('one city, town or village', $o[0]['exception']);
    }

    /** Windstorm carries no place condition — the clock alone decides. */
    public function test_windstorm_has_no_place_condition(): void
    {
        $this->assertNull($this->calc->localityConditionFor('Windstorm'));

        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Windstorm', 'ceded' => 1000, 'locality' => 'Gaborone'],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Windstorm', 'ceded' => 1000, 'locality' => 'Maun'],
        ]);

        $this->assertCount(1, $o, 'a storm crosses towns');
    }

    // ───────────────────────────────────────── grouping

    /** Two windstorm losses inside 72 hours are one occurrence. */
    public function test_losses_inside_the_window_are_one_occurrence(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Windstorm', 'ceded' => 10000000, 'reference' => 'A'],
            ['occurred_at' => '2026-07-03 05:00:00', 'peril' => 'Windstorm', 'ceded' => 5000000,  'reference' => 'B'],
        ]);

        $this->assertCount(1, $o);
        $this->assertSame(2, $o[0]['loss_count']);
        $this->assertSame(15000000.0, $o[0]['ceded_sought']);
        $this->assertSame(['A', 'B'], $o[0]['references']);
    }

    /** One hour past the window and it is a second occurrence, paid separately. */
    public function test_a_loss_past_the_window_is_a_second_occurrence(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Windstorm', 'ceded' => 10000000],
            ['occurred_at' => '2026-07-04 07:00:00', 'peril' => 'Windstorm', 'ceded' => 5000000],
        ]);

        $this->assertCount(2, $o);
    }

    /**
     * The window length belongs to the peril, so the same gap splits one peril
     * and not another. 100 hours apart: two windstorms, one theft.
     */
    public function test_the_same_gap_splits_one_peril_and_not_another(): void
    {
        $storms = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 00:00:00', 'peril' => 'Windstorm', 'ceded' => 1000],
            ['occurred_at' => '2026-07-05 04:00:00', 'peril' => 'Windstorm', 'ceded' => 1000],
        ]);
        $thefts = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 00:00:00', 'peril' => 'Theft', 'ceded' => 1000],
            ['occurred_at' => '2026-07-05 04:00:00', 'peril' => 'Theft', 'ceded' => 1000],
        ]);

        $this->assertCount(2, $storms, '100 hours splits a 72-hour peril');
        $this->assertCount(1, $thefts, 'and not a 168-hour one');
    }

    /** Different perils are never one occurrence, however close in time. */
    public function test_two_perils_at_the_same_moment_are_two_occurrences(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Windstorm', 'ceded' => 1000],
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Theft',     'ceded' => 1000],
        ]);

        $this->assertCount(2, $o);
    }

    // ───────────────────────────────────────── fire's radius

    /** Fire inside 75 hours AND 80km is one occurrence. */
    public function test_fires_close_in_time_and_place_are_one_occurrence(): void
    {
        $o = $this->calc->occurrencesFor([
            // Gaborone, and about 12km away.
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'latitude' => -24.6282, 'longitude' => 25.9231],
            ['occurred_at' => '2026-07-03 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'latitude' => -24.7200, 'longitude' => 25.9300],
        ]);

        $this->assertCount(1, $o);
        $this->assertSame(75, $o[0]['hours_clause']);
    }

    /**
     * Fire inside 75 hours but OUTSIDE 80km is two occurrences. This is the case
     * that makes the radius worth computing: on time alone they would merge and
     * the treaty would pay once instead of twice.
     */
    public function test_fires_far_apart_are_two_occurrences_however_close_in_time(): void
    {
        $o = $this->calc->occurrencesFor([
            // Gaborone and Francistown, about 430km apart.
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'latitude' => -24.6282, 'longitude' => 25.9231],
            ['occurred_at' => '2026-07-01 08:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'latitude' => -21.1700, 'longitude' => 27.5100],
        ]);

        $this->assertCount(2, $o, 'two hours apart, but 430km');
    }

    /** With no coordinates, a shared location key is evidence enough. */
    public function test_fires_at_the_same_named_location_group(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'location' => 'Plot 54, Gaborone'],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'location' => 'plot 54, gaborone'],
        ]);

        $this->assertCount(1, $o);
        $this->assertArrayNotHasKey('radius_untested', $o[0]);
    }

    public function test_fires_at_different_named_locations_do_not_group(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'location' => 'Gaborone'],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Fire', 'ceded' => 1000, 'location' => 'Maun'],
        ]);

        $this->assertCount(2, $o);
    }

    /**
     * Unknown geography is flagged, never assumed. Grouping on time alone may
     * merge occurrences the clause would separate, which understates what the
     * treaty owes — so the occurrence says so rather than reading as settled.
     */
    public function test_fire_with_no_geography_is_grouped_on_time_and_flagged(): void
    {
        $o = $this->calc->occurrencesFor([
            ['occurred_at' => '2026-07-01 06:00:00', 'peril' => 'Fire', 'ceded' => 1000],
            ['occurred_at' => '2026-07-02 06:00:00', 'peril' => 'Fire', 'ceded' => 1000],
        ]);

        $this->assertCount(1, $o);
        $this->assertTrue($o[0]['place_untested']);
        $this->assertStringContainsString('80km radius could not be tested', $o[0]['exception']);
    }

    // ───────────────────────────────────────── the limit

    /** The cap bites, and the excess is declined rather than quietly ceded. */
    public function test_the_event_limit_caps_the_ceded_recovery(): void
    {
        $o = $this->calc->applyLimit(
            ['peril' => 'Windstorm', 'ceded_sought' => 105000000.0],
            'general'
        );

        $this->assertSame(70000000.0, $o['ceded_recovered']);
        $this->assertSame(35000000.0, $o['ceded_declined']);
        $this->assertTrue($o['limit_exhausted']);
    }

    /** Below the limit nothing is declined. */
    public function test_an_occurrence_within_the_limit_is_paid_in_full(): void
    {
        $o = $this->calc->applyLimit(
            ['peril' => 'Theft', 'ceded_sought' => 4000000.0],
            'general'
        );

        $this->assertSame(4000000.0, $o['ceded_recovered']);
        $this->assertSame(0.0, $o['ceded_declined']);
        $this->assertFalse($o['limit_exhausted']);
    }

    /**
     * RI-02 blocker 3, in one assertion. A 150,000,000 occurrence cedes
     * 70,000,000 and leaves 80,000,000 net to Alpha Direct — which is a
     * catastrophe-cover question, and the reason the Cat XL programme matters.
     */
    public function test_the_hundred_and_fifty_million_occurrence(): void
    {
        $o = $this->calc->applyLimit(
            ['peril' => 'Windstorm', 'ceded_sought' => 150000000.0],
            'general'
        );

        $this->assertSame(70000000.0, $o['ceded_recovered']);
        $this->assertSame(80000000.0, $o['ceded_declined'], 'net to Alpha Direct');
    }

    /** Motor riot and strike is capped below the Motor treaty's own event limit. */
    public function test_motor_riot_and_strike_carries_a_lower_limit(): void
    {
        $this->assertSame(50000000.0, $this->calc->limitFor('motor', 'Riot and strike'));
        $this->assertSame(70000000.0, $this->calc->limitFor('motor', 'Theft'));
        $this->assertSame(70000000.0, $this->calc->limitFor('motor'));
    }

    /** The surplus carries its own, higher limit — Final Terms, Prop sheet. */
    public function test_the_surplus_has_its_own_event_limit(): void
    {
        $this->assertSame(120000000.0, $this->calc->limitFor('surplus'));
    }

    public function test_an_unconfigured_treaty_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No event limit configured for treaty 'xol'");

        $this->calc->limitFor('xol');
    }

    // ──────────────────────────────────────── Note 5: an event across two years

    /**
     * An event straddling two underwriting years does not get the whole limit.
     *
     * Note 5, quoting General Article 5.9: the limit "is reduced in the same
     * proportion as the losses of that event involving the agreement contribute
     * to the sum of all losses of that event across all underwriting years".
     *
     * 60,000,000 of this year against 100,000,000 across both is 60%, so the
     * 70,000,000 limit becomes 42,000,000. The full limit would recover more from
     * this year's treaty than this year's business contributed to the loss.
     */
    public function test_the_limit_reduces_in_proportion_across_years(): void
    {
        $this->assertSame(
            42000000.0,
            $this->calc->limitForStraddlingEvent('general', 60000000.0, 100000000.0)
        );
    }

    /** Wholly within one year, the full limit stands. */
    public function test_a_single_year_event_keeps_the_whole_limit(): void
    {
        $this->assertSame(
            70000000.0,
            $this->calc->limitForStraddlingEvent('general', 100000000.0, 100000000.0)
        );
    }

    /** The reduction rides on whichever limit the peril and treaty give. */
    public function test_the_reduction_applies_to_the_motor_riot_limit_too(): void
    {
        $this->assertSame(
            25000000.0,
            $this->calc->limitForStraddlingEvent('motor', 50000000.0, 100000000.0, 'Riot'),
            'half of the 50,000,000 riot limit'
        );
    }

    /** Applying it reports what was reduced, not only the reduced figure. */
    public function test_applying_a_straddling_limit_says_it_straddled(): void
    {
        $o = $this->calc->applyStraddlingLimit(
            ['peril' => 'Windstorm', 'ceded_sought' => 90000000.0],
            'general',
            60000000.0,
            100000000.0
        );

        $this->assertTrue($o['straddles_years']);
        $this->assertSame(70000000.0, $o['event_limit_full']);
        $this->assertSame(42000000.0, $o['event_limit']);
        $this->assertSame(42000000.0, $o['ceded_recovered']);
        $this->assertSame(48000000.0, $o['ceded_declined']);
        $this->assertSame(0.6, $o['agreement_share']);
    }

    public function test_a_single_year_occurrence_is_not_marked_as_straddling(): void
    {
        $o = $this->calc->applyStraddlingLimit(
            ['peril' => 'Theft', 'ceded_sought' => 1000.0],
            'general',
            5000000.0,
            5000000.0
        );

        $this->assertFalse($o['straddles_years']);
        $this->assertSame(70000000.0, $o['event_limit']);
    }

    /**
     * @dataProvider badApportionments
     */
    public function test_an_impossible_apportionment_is_refused(float $mine, float $all): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->limitForStraddlingEvent('general', $mine, $all);
    }

    public static function badApportionments(): array
    {
        return [
            'no losses at all'      => [0.0, 0.0],
            'mine exceeds the total' => [200.0, 100.0],
            'mine is negative'       => [-1.0, 100.0],
        ];
    }

    // ───────────────────────────────────────── end to end

    /**
     * A storm sequence and an unrelated theft, assessed together.
     *
     * The three storm losses fall in one 72-hour window and sum to 90,000,000,
     * so the treaty pays 70,000,000 and declines 20,000,000. The theft is its
     * own occurrence and is paid in full.
     */
    public function test_it_assesses_a_mixed_set_of_losses(): void
    {
        $a = $this->calc->assess([
            ['occurred_at' => '2026-07-01 02:00:00', 'peril' => 'Windstorm', 'ceded' => 40000000, 'reference' => 'S1'],
            ['occurred_at' => '2026-07-02 02:00:00', 'peril' => 'Windstorm', 'ceded' => 30000000, 'reference' => 'S2'],
            ['occurred_at' => '2026-07-03 02:00:00', 'peril' => 'Windstorm', 'ceded' => 20000000, 'reference' => 'S3'],
            ['occurred_at' => '2026-07-02 09:00:00', 'peril' => 'Theft',     'ceded' => 1500000,  'reference' => 'T1'],
        ], 'general');

        $this->assertSame(2, $a['totals']['occurrences']);
        $this->assertSame(91500000.0, $a['totals']['ceded_sought']);
        $this->assertSame(71500000.0, $a['totals']['ceded_recovered']);
        $this->assertSame(20000000.0, $a['totals']['ceded_declined']);
    }

    public function test_no_losses_is_no_occurrences(): void
    {
        $this->assertSame([], $this->calc->occurrencesFor([]));
        $this->assertSame(0, $this->calc->assess([], 'general')['totals']['occurrences']);
    }

    /** A loss with no readable date is refused, not silently dropped. */
    public function test_a_loss_with_no_date_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('LOSS-9');

        $this->calc->occurrencesFor([
            ['occurred_at' => '', 'peril' => 'Fire', 'ceded' => 1000, 'reference' => 'LOSS-9'],
        ]);
    }

    /** Limits are configurable, because a treaty year changes them. */
    public function test_limits_can_be_configured(): void
    {
        $calc = new EventLimitCalculator(['general' => 25000000.0]);

        $this->assertSame(25000000.0, $calc->limitFor('general'));
        $this->assertSame(70000000.0, $calc->limitFor('motor'), 'the others are untouched');
    }

    public function test_a_negative_limit_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventLimitCalculator(['general' => -1.0]);
    }
}
