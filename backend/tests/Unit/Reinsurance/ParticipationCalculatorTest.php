<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Reinsurance\ReinsuranceEngine;
use AlphaDirect\Services\Reinsurance\ParticipationCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The two bases a participation can be quoted on, and what a short panel means.
 *
 * The conversion is one line of arithmetic. Everything difficult here is about
 * refusing to paper over an incomplete signing schedule, because the shapes of
 * being wrong are not symmetrical: an over-stated panel is caught by the engine's
 * own gate, while a short panel scaled up to 100% reports cover nobody wrote and
 * is caught by nothing at all.
 *
 * Figures are the real ones from the signed slips, via RI-02 blockers 1 and 3.
 */
class ParticipationCalculatorTest extends TestCase
{
    private ParticipationCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new ParticipationCalculator(0.70);
    }

    // ───────────────────────────────────────── the conversion

    /**
     * GIC Re SA, from the Motor slip: signed "17% of cession", which on a 70%
     * cession is 11.90% of the whole risk. RI-02 blocker 3 names this line as the
     * one that proves the two bases are both in use.
     */
    public function test_the_gic_re_line_converts_both_ways(): void
    {
        $this->assertSame(11.9, $this->calc->toHundredBasis(17.0));
        $this->assertSame(17.0, $this->calc->toCessionBasis(11.9));
    }

    /** Reading one basis as the other misstates the share by 1/0.70. */
    public function test_reading_the_wrong_basis_misstates_by_the_cession(): void
    {
        $ofCession = $this->calc->toCessionBasis(29.0);   // FM Re, 29% of 100%

        $this->assertSame(41.428571, $ofCession);
        $this->assertEqualsWithDelta(1.4286, $ofCession / 29.0, 0.0001, 'out by 1/0.70');
    }

    /** A fully placed panel sums to the cession, not to 100. */
    public function test_a_full_panel_sums_to_the_cession(): void
    {
        $this->assertSame(70.0, $this->calc->fullPanelOnHundredBasis());
        $this->assertSame(50.0, (new ParticipationCalculator(0.50))->fullPanelOnHundredBasis());
    }

    // ───────────────────────────────────────── panels

    /** Both bases on one panel, because the slips do exactly that. */
    public function test_a_panel_can_mix_the_two_bases(): void
    {
        $p = $this->calc->assessPanel([
            ['reinsurer' => 'Continental Re', 'share_pct' => 25.0, 'basis' => ParticipationCalculator::BASIS_HUNDRED],
            ['reinsurer' => 'GIC Re SA',      'share_pct' => 17.0, 'basis' => ParticipationCalculator::BASIS_CESSION],
        ]);

        $this->assertSame(25.0, $p['participations'][0]['share_of_hundred']);
        $this->assertSame(11.9, $p['participations'][1]['share_of_hundred']);
        $this->assertSame(36.9, $p['placed_of_hundred'], 'the real Motor panel');
    }

    /** The 100% basis is the default, per the 31 August confirmation. */
    public function test_the_hundred_basis_is_assumed_when_none_is_stated(): void
    {
        $p = $this->calc->assessPanel([['reinsurer' => 'FM Re', 'share_pct' => 29.0]]);

        $this->assertSame(ParticipationCalculator::BASIS_HUNDRED, $p['participations'][0]['stated_basis']);
        $this->assertSame(29.0, $p['participations'][0]['share_of_hundred']);
    }

    /** A panel adding to the cession is complete, and says nothing. */
    public function test_a_complete_panel_raises_nothing(): void
    {
        $p = $this->calc->assessPanel([
            ['reinsurer' => 'A', 'share_pct' => 40.0],
            ['reinsurer' => 'B', 'share_pct' => 30.0],
        ]);

        $this->assertTrue($p['complete']);
        $this->assertSame(70.0, $p['placed_of_hundred']);
        $this->assertSame(100.0, $p['placed_of_cession'], 'which is 100% of the cession');
        $this->assertNull($p['exception']);
    }

    // ───────────────────────────────────────── the real, short panels

    /**
     * THE GENERAL PANEL AS IT STANDS. FM Re signed 29% of 100%, PBC Re 10% of
     * cession — 7% of 100%. Together 36% against a 70% cession, so roughly half
     * the cession has no reinsurer behind it.
     */
    public function test_the_general_panel_is_short_by_thirty_four_points(): void
    {
        $p = $this->calc->assessPanel([
            ['reinsurer' => 'FM Re',  'share_pct' => 29.0],
            ['reinsurer' => 'PBC Re', 'share_pct' => 10.0, 'basis' => ParticipationCalculator::BASIS_CESSION],
        ]);

        $this->assertSame(36.0, $p['placed_of_hundred']);
        $this->assertSame(34.0, $p['unplaced_of_hundred']);
        $this->assertFalse($p['complete']);
        $this->assertStringContainsString('no reinsurer behind it', $p['exception']);
    }

    /** And the Motor panel, 36.90% against 70%. */
    public function test_the_motor_panel_is_short_by_thirty_three_points(): void
    {
        $p = $this->calc->assessPanel([
            ['reinsurer' => 'Continental Re', 'share_pct' => 25.0],
            ['reinsurer' => 'GIC Re SA',      'share_pct' => 17.0, 'basis' => ParticipationCalculator::BASIS_CESSION],
        ]);

        $this->assertSame(36.9, $p['placed_of_hundred']);
        $this->assertSame(33.1, $p['unplaced_of_hundred']);
        $this->assertFalse($p['complete']);
    }

    /**
     * Over-placement is a different fault and says so: a share on the wrong basis,
     * or one line recorded twice.
     */
    public function test_an_over_placed_panel_is_reported_differently(): void
    {
        $p = $this->calc->assessPanel([
            ['reinsurer' => 'A', 'share_pct' => 50.0],
            ['reinsurer' => 'B', 'share_pct' => 40.0],
        ]);

        $this->assertFalse($p['complete']);
        $this->assertSame(-20.0, $p['unplaced_of_hundred']);
        $this->assertStringContainsString('OVER-placed', $p['exception']);
        $this->assertStringContainsString('recorded twice', $p['exception']);
    }

    // ───────────────────────────────────────── the split, and the refusal

    /** A complete panel converts to shares of the cession summing to 100. */
    public function test_a_complete_panel_produces_a_split_the_engine_accepts(): void
    {
        $shares = $this->calc->splitShares([
            ['reinsurer_id' => 'FMRE', 'share_pct' => 42.0],
            ['reinsurer_id' => 'PBC',  'share_pct' => 28.0],
        ]);

        $this->assertSame(60.0, $shares[0]['share_pct']);
        $this->assertSame(40.0, $shares[1]['share_pct']);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($shares, 'share_pct')), 1e-6);

        // And the engine takes it without complaint.
        $split = ReinsuranceEngine::splitByReinsurers(7000000.0, $shares);
        $this->assertSame(4200000.0, $split[0]['amount']);
        $this->assertSame(2800000.0, $split[1]['amount']);
    }

    /**
     * THE SAFETY PROPERTY. A short panel is refused rather than scaled to 100%.
     * Scaling would hand the whole ceded amount to reinsurers who between them
     * signed for half of it.
     */
    public function test_a_short_panel_refuses_to_produce_a_split(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not fully placed');

        $this->calc->splitShares([
            ['reinsurer_id' => 'FMRE', 'share_pct' => 29.0],
            ['reinsurer_id' => 'PBC',  'share_pct' => 10.0, 'basis' => ParticipationCalculator::BASIS_CESSION],
        ]);
    }

    /** The refusal names the figures, so it can be acted on rather than puzzled over. */
    public function test_the_refusal_states_the_shortfall(): void
    {
        try {
            $this->calc->splitShares([['reinsurer_id' => 'FMRE', 'share_pct' => 29.0]]);
            $this->fail('a 29% panel should not produce a split');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('29', $e->getMessage());
            $this->assertStringContainsString('70', $e->getMessage());
            $this->assertStringContainsString('41', $e->getMessage(), 'the shortfall');
        }
    }

    // ───────────────────────────────────────── guards

    public function test_an_unknown_basis_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("unknown basis 'of_something'");

        $this->calc->assessPanel([['share_pct' => 10.0, 'basis' => 'of_something']]);
    }

    public function test_a_negative_share_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc->assessPanel([['share_pct' => -5.0]]);
    }

    /**
     * @dataProvider badCessions
     */
    public function test_an_impossible_cession_is_refused(float $cession): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParticipationCalculator($cession);
    }

    public static function badCessions(): array
    {
        return ['zero' => [0.0], 'negative' => [-0.1], 'over one' => [1.5]];
    }

    /** An empty panel is short by the whole cession, not vacuously complete. */
    public function test_an_empty_panel_is_short_by_the_whole_cession(): void
    {
        $p = $this->calc->assessPanel([]);

        $this->assertSame(0.0, $p['placed_of_hundred']);
        $this->assertSame(70.0, $p['unplaced_of_hundred']);
        $this->assertFalse($p['complete']);
    }
}
