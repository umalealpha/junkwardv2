<?php

namespace Tests\Unit\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The quarter arithmetic and the two clocks — RI-18 step 1.
 *
 * Nothing here touches a database. What is being proved is the arithmetic that
 * decides which dates a statement is judged against, because a quarter derived
 * wrongly puts a whole statement on the wrong side of a deadline and nothing
 * downstream would notice.
 *
 * THE TREATY YEAR RUNS JULY TO JUNE, so quarter 1 of 2026/27 is Jul–Sep 2026 and
 * quarter 4 ends 30 June 2027. Accounts are rendered within 45 days of the close
 * (BR-ACC-01) and confirmed within 14 of being rendered (BR-ACC-02).
 */
class TreatyStatementPeriodTest extends TestCase
{
    /**
     * A statement with attributes set RAW, bypassing the casts.
     *
     * Eloquent applies date casts on write as well as on read, and both resolve
     * through the connection — so `new TreatyStatement(['render_due' => ...])`
     * needs a database to hold a string. setRawAttributes() is the documented
     * way past that, and it keeps these tests what they should be: arithmetic
     * on two dates, with nothing stood up to hold them.
     *
     * @param array<string,mixed> $attrs
     */
    private function statement(array $attrs): TreatyStatement
    {
        return (new TreatyStatement())->setRawAttributes($attrs);
    }

    /**
     * @dataProvider quarters
     */
    public function test_the_quarters_of_a_treaty_year(
        int $quarter,
        string $start,
        string $end,
        string $renderDue
    ): void {
        $p = TreatyStatement::periodFor(2026, $quarter);

        $this->assertSame($start, $p['start']);
        $this->assertSame($end, $p['end']);
        $this->assertSame($renderDue, $p['render_due']);
    }

    public static function quarters(): array
    {
        return [
            'Q1 Jul–Sep' => [1, '2026-07-01', '2026-09-30', '2026-11-14'],
            'Q2 Oct–Dec' => [2, '2026-10-01', '2026-12-31', '2027-02-14'],
            'Q3 Jan–Mar' => [3, '2027-01-01', '2027-03-31', '2027-05-15'],
            'Q4 Apr–Jun' => [4, '2027-04-01', '2027-06-30', '2027-08-14'],
        ];
    }

    /**
     * THE FIRST DEADLINE, pinned on its own because everything is measured
     * against it. Q1 closes 30 September 2026, so accounts are due 14 November.
     *
     * THAT IS A SATURDAY. The contractual date is the 14th and this returns the
     * 14th — it is not quietly shifted, because a rule that moves a contractual
     * date to suit a calendar is a rule nobody agreed. Issuing on Friday the
     * 13th is an operational decision and belongs in the plan, not in here.
     */
    public function test_the_first_statement_is_due_on_the_fourteenth_of_november(): void
    {
        $p = TreatyStatement::periodFor(2026, 1);

        $this->assertSame('2026-11-14', $p['render_due']);
        $this->assertSame('Saturday', date('l', strtotime($p['render_due'])));
    }

    /** A quarter 4 rolls into the next calendar year without wrapping wrongly. */
    public function test_the_year_boundary_does_not_wrap(): void
    {
        $this->assertSame('2027-04-01', TreatyStatement::periodFor(2026, 4)['start']);
        $this->assertSame('2027-07-01', TreatyStatement::periodFor(2027, 1)['start']);
    }

    /**
     * @dataProvider badQuarters
     */
    public function test_an_impossible_quarter_is_refused(int $q): void
    {
        $this->expectException(InvalidArgumentException::class);

        TreatyStatement::periodFor(2026, $q);
    }

    public static function badQuarters(): array
    {
        return ['zero' => [0], 'five' => [5], 'negative' => [-1]];
    }

    // ───────────────────────────────────────────── the balance

    /**
     * OUTSTANDING LOSSES ARE REPORTED, NOT SETTLED. Article 10.2.4 puts them in
     * the statement because reinsurers need the reserve position, but they are
     * not money moving this quarter. Adding them would overstate the balance by
     * the whole outstanding book.
     */
    public function test_outstanding_losses_are_memorandum_only(): void
    {
        $item = (new TreatyStatementItem())->setRawAttributes(
            ['item_type' => TreatyStatementItem::OUTSTANDING_LOSSES]
        );

        $this->assertTrue($item->isMemorandumOnly());
    }

    /**
     * Salvages and recoveries are excluded from the balance too, but for a
     * different reason: they are already netted inside claims_paid. Counting
     * them again would relieve the reinsurer twice.
     */
    public function test_salvages_and_recoveries_are_memorandum_only(): void
    {
        foreach ([TreatyStatementItem::SALVAGES, TreatyStatementItem::RECOVERIES] as $type) {
            $this->assertTrue(
                (new TreatyStatementItem())->setRawAttributes(['item_type' => $type])->isMemorandumOnly()
            );
        }
    }

    /** Everything that does move money is not. */
    public function test_the_settling_items_are_not_memorandum(): void
    {
        foreach ([
            TreatyStatementItem::PREMIUM,
            TreatyStatementItem::COMMISSION,
            TreatyStatementItem::CLAIMS_PAID,
            TreatyStatementItem::BROKERAGE,
            TreatyStatementItem::VAT,
            TreatyStatementItem::RESERVE_DEPOSIT,
            TreatyStatementItem::DELAY_INTEREST,
        ] as $type) {
            $this->assertFalse(
                (new TreatyStatementItem())->setRawAttributes(['item_type' => $type])->isMemorandumOnly(),
                "{$type} moves money and must enter the balance"
            );
        }
    }

    // ───────────────────────────────────────────── which treaty

    /**
     * THE SPLIT IS BY GROUP, NOT BY REGULATORY CLASS. RI-05 lists the two
     * treaties by group; the class is what a statement reports by (BR-RPT-02).
     * Several groups share a class, so splitting on the class is a coarser
     * question that happens to agree on today's book and stops agreeing the
     * moment a group's class does not match its treaty.
     *
     * @dataProvider groups
     */
    public function test_which_treaty_covers_a_group(string $group, string $treaty): void
    {
        $this->assertTrue(TreatyStatement::coversGroup($treaty, $group));
        $this->assertFalse(
            TreatyStatement::coversGroup($treaty === 'motor' ? 'general' : 'motor', $group),
            "{$group} must not appear on both statements"
        );
    }

    public static function groups(): array
    {
        return [
            // RI-05's four named Motor-treaty groups.
            'own damage vehicle'   => ['MOTOR_COM', 'motor'],
            'own damage trailer'   => ['MOTOR_TRAILERS_COM', 'motor'],
            'third party external' => ['MOTOR_TRADERS_COM_EXT', 'motor'],
            'third party internal' => ['MOTOR_TRADERS_COM_INT', 'motor'],
            // In the group table and motor business, treaty inclusion pending.
            'domestic vehicle'     => ['MOTOR_DOM', 'motor'],
            'domestic trailer'     => ['MOTOR_TRAILERS_DOM', 'motor'],
            // RI-05's General-treaty groups.
            'property'             => ['PROPERTYANDBI_COM', 'general'],
            'goods in transit'     => ['GOODSINTRANSIT_COM', 'general'],
            'fidelity'             => ['FIDELITYG_COM', 'general'],
            'miscellaneous'        => ['MISC_COM', 'general'],
            'electronic equipment' => ['ELECTRONIC_EQ_AND_BI_COM', 'general'],
            // Not in either RI-05 list, but plainly not motor.
            'workmen compensation' => ['WC_COM', 'general'],
        ];
    }

    /**
     * MOTOR_TRADERS_COM_INT (group 30) is Motor HERE and outside
     * CessionSource::MOTOR_GROUP_IDS there, and both are correct.
     *
     * That constant reproduces the legacy motor tab verbatim, because the
     * cession seam is a move rather than a rewrite; this list answers a
     * different question — which treaty reports a cession — and RI-05 is its
     * authority. Pinned so the statement split does not quietly inherit the
     * legacy tab's shape.
     */
    public function test_a_motor_group_outside_the_legacy_tab_is_still_motor_here(): void
    {
        $this->assertTrue(TreatyStatement::coversGroup('motor', 'MOTOR_TRADERS_COM_INT'));
    }

    /**
     * AN UNKNOWN GROUP FALLS TO GENERAL RATHER THAN TO NOTHING. Dropping it from
     * both statements would make the money disappear silently. Seven regulatory
     * rows carry a blank group_code on the test server.
     */
    public function test_an_unknown_group_falls_to_general_and_is_flagged(): void
    {
        foreach (['', null, 'SOMETHING_NEW'] as $group) {
            $this->assertTrue(TreatyStatement::coversGroup('general', $group));
            $this->assertFalse(TreatyStatement::coversGroup('motor', $group));
        }

        $this->assertFalse(TreatyStatement::isClassifiableGroup(''));
        $this->assertFalse(TreatyStatement::isClassifiableGroup(null));
        $this->assertTrue(TreatyStatement::isClassifiableGroup('MOTOR_COM'));
    }

    // ───────────────────────────────────────────── lateness

    /**
     * An unrendered statement is judged against today; a rendered one against
     * the day it was rendered. Both matter, because BR-ACC-10 charges interest
     * from the due date to the date of payment — lateness has to be measurable
     * before settlement and after it.
     */
    public function test_an_unrendered_statement_is_judged_against_today(): void
    {
        $s = $this->statement(['render_due' => '2026-11-14']);

        $early = $s->renderLateness('2026-11-01');
        $this->assertFalse($early['late']);
        $this->assertSame('today', $early['against']);

        $late = $s->renderLateness('2026-11-20');
        $this->assertTrue($late['late']);
        $this->assertSame(6, $late['days']);
    }

    public function test_a_statement_rendered_on_time_is_never_late(): void
    {
        $s = $this->statement([
            'render_due'  => '2026-11-14',
            'rendered_at' => '2026-11-13 16:00:00',
        ]);

        $r = $s->renderLateness('2027-03-01');   // long after, and still not late

        $this->assertFalse($r['late']);
        $this->assertSame('rendered', $r['against']);
    }

    public function test_a_statement_rendered_late_stays_late(): void
    {
        $s = $this->statement([
            'render_due'  => '2026-11-14',
            'rendered_at' => '2026-11-24 09:00:00',
        ]);

        $r = $s->renderLateness();

        $this->assertTrue($r['late']);
        $this->assertSame(10, $r['days']);
    }

    /** No due date is not "on time" — it is unmeasurable, and says so. */
    public function test_a_statement_with_no_due_date_reports_no_due_date(): void
    {
        $r = $this->statement([])->renderLateness();

        $this->assertFalse($r['late']);
        $this->assertSame('no due date', $r['against']);
    }
}
