<?php

namespace Tests\Unit\ClaimSla;

use AlphaDirect\Services\ClaimSla\WorkingDayCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins the claims SLA WORKING-DAY arithmetic against a
 * Mon–Fri working week (the claims engine's default) plus the half-day Saturday
 * week the claims team actually works, with holidays injected.
 */
class WorkingDayCalculatorTest extends TestCase
{
    private function calc(array $holidays = []): WorkingDayCalculator
    {
        // Mon–Fri working week, Gaborone tz.
        return new WorkingDayCalculator([1, 2, 3, 4, 5], $holidays, 'Africa/Gaborone');
    }

    /** Mon–Sat week with Saturday worth half a working day (Sun closed). */
    private function halfSatCalc(array $holidays = []): WorkingDayCalculator
    {
        return new WorkingDayCalculator([1, 2, 3, 4, 5, 6], $holidays, 'Africa/Gaborone', [6 => 0.5]);
    }

    public function test_weekend_is_not_a_working_day(): void
    {
        $calc = $this->calc();
        $this->assertTrue($calc->isWorkingDay(Carbon::parse('2026-07-20')));   // Monday
        $this->assertFalse($calc->isWorkingDay(Carbon::parse('2026-07-25')));  // Saturday
        $this->assertFalse($calc->isWorkingDay(Carbon::parse('2026-07-26')));  // Sunday
    }

    public function test_holiday_is_not_a_working_day(): void
    {
        $calc = $this->calc(['2026-07-21']); // Tue holiday
        $this->assertFalse($calc->isWorkingDay(Carbon::parse('2026-07-21')));
        $this->assertTrue($calc->isWorkingDay(Carbon::parse('2026-07-22')));  // Wed
    }

    public function test_add_working_days_skips_weekend(): void
    {
        $calc = $this->calc();
        // Fri 2026-07-24 + 2 working days => Tue 2026-07-28 (skip Sat/Sun).
        $due = $calc->addWorkingDays(Carbon::parse('2026-07-24'), 2);
        $this->assertSame('2026-07-28', $due->toDateString());
    }

    public function test_add_working_days_skips_holiday(): void
    {
        // Wed 2026-07-22 holiday. Start Mon 2026-07-20 + 2 wd:
        // +1 => Tue 21, +2 would be Wed 22 (holiday) => Thu 23.
        $calc = $this->calc(['2026-07-22']);
        $due  = $calc->addWorkingDays(Carbon::parse('2026-07-20'), 2);
        $this->assertSame('2026-07-23', $due->toDateString());
    }

    public function test_add_zero_returns_start_working_day(): void
    {
        $calc = $this->calc();
        // Start on a Saturday, +0 => next working day (Mon).
        $due = $calc->addWorkingDays(Carbon::parse('2026-07-25'), 0);
        $this->assertSame('2026-07-27', $due->toDateString());
    }

    public function test_working_days_between_is_symmetric_with_add(): void
    {
        $calc  = $this->calc(['2026-07-22']);
        $start = Carbon::parse('2026-07-20');
        foreach ([1, 2, 3, 5, 10, 17] as $n) {
            $due = $calc->addWorkingDays($start, $n);
            // Full-day weeks: the round-trip is exact.
            $this->assertEqualsWithDelta($n, $calc->workingDaysBetween($start, $due), 1e-9, "round-trip for n=$n");
        }
    }

    public function test_working_days_between_counts_only_working_days(): void
    {
        $calc = $this->calc();
        // Mon 20 -> Mon 27 spans one full week = 5 working days (Sat/Sun excluded).
        $this->assertEqualsWithDelta(5.0, $calc->workingDaysBetween(Carbon::parse('2026-07-20'), Carbon::parse('2026-07-27')), 1e-9);
        // Same day / reversed => 0.
        $this->assertEqualsWithDelta(0.0, $calc->workingDaysBetween(Carbon::parse('2026-07-20'), Carbon::parse('2026-07-20')), 1e-9);
        $this->assertEqualsWithDelta(0.0, $calc->workingDaysBetween(Carbon::parse('2026-07-27'), Carbon::parse('2026-07-20')), 1e-9);
    }

    // ── Half-day Saturday ────────────────────────────────────────────────────

    public function test_saturday_is_a_working_day_worth_half(): void
    {
        $calc = $this->halfSatCalc();
        $this->assertTrue($calc->isWorkingDay(Carbon::parse('2026-07-25')));            // Saturday works…
        $this->assertEqualsWithDelta(0.5, $calc->weightForDate(Carbon::parse('2026-07-25')), 1e-9); // …at half weight
        $this->assertEqualsWithDelta(1.0, $calc->weightForDate(Carbon::parse('2026-07-24')), 1e-9); // Friday full
        $this->assertEqualsWithDelta(0.0, $calc->weightForDate(Carbon::parse('2026-07-26')), 1e-9); // Sunday closed
    }

    public function test_half_saturday_week_totals_five_and_a_half(): void
    {
        $calc = $this->halfSatCalc();
        // Mon 20 -> Mon 27: Tue..Fri (4.0) + Sat (0.5) + Sun (0) + Mon (1.0) = 5.5.
        $this->assertEqualsWithDelta(5.5, $calc->workingDaysBetween(Carbon::parse('2026-07-20'), Carbon::parse('2026-07-27')), 1e-9);
    }

    public function test_half_saturday_defers_deadline(): void
    {
        $calc = $this->halfSatCalc();
        // Thu 23 + 2 wd: Fri (1.0) + Sat (1.5) not enough; Mon 27 reaches 2.0.
        $this->assertSame('2026-07-27', $calc->addWorkingDays(Carbon::parse('2026-07-23'), 2)->toDateString());
        // Fri 24 + 1 wd: Saturday's half alone doesn't complete a day => Mon 27.
        $this->assertSame('2026-07-27', $calc->addWorkingDays(Carbon::parse('2026-07-24'), 1)->toDateString());
        // A half-day deadline lands on the Saturday itself.
        $this->assertSame('2026-07-25', $calc->addWorkingDays(Carbon::parse('2026-07-24'), 0.5)->toDateString());
    }

    public function test_default_weights_match_legacy_integer_behaviour(): void
    {
        // With no weight overrides, an open day weighs a full 1.0 — identical to
        // the previous integer calculator.
        $calc = new WorkingDayCalculator([1, 2, 3, 4, 5], [], 'Africa/Gaborone');
        $this->assertEqualsWithDelta(1.0, $calc->weightForDate(Carbon::parse('2026-07-24')), 1e-9);
        $this->assertSame('2026-07-28', $calc->addWorkingDays(Carbon::parse('2026-07-24'), 2)->toDateString());
    }
}
