<?php

namespace Tests\Unit;

use AlphaDirect\Services\BusinessHoursCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins the SLA business-hours arithmetic against the
 * default Botswana calendar:
 *   Mon–Fri 08:00–17:00, Sat 08:00–13:00, Sun closed.
 *
 * Note: the engine counts whatever windows the calendar defines, so Saturday's
 * 5 hours ARE business time. The "1 business day = 9h" assumption only affects
 * how the seeded matrix converts "N business days" into target minutes.
 */
class BusinessHoursCalculatorTest extends TestCase
{
    private function calc(array $holidays = []): BusinessHoursCalculator
    {
        $schedule = [
            1 => ['open' => '08:00', 'close' => '17:00'], // Mon
            2 => ['open' => '08:00', 'close' => '17:00'], // Tue
            3 => ['open' => '08:00', 'close' => '17:00'], // Wed
            4 => ['open' => '08:00', 'close' => '17:00'], // Thu
            5 => ['open' => '08:00', 'close' => '17:00'], // Fri
            6 => ['open' => '08:00', 'close' => '13:00'], // Sat
            // Sun absent => closed
        ];
        return new BusinessHoursCalculator($schedule, $holidays, 'Africa/Gaborone');
    }

    private function moment(string $iso): Carbon
    {
        return Carbon::parse($iso, 'Africa/Gaborone');
    }

    public function test_is_open_basic(): void
    {
        $c = $this->calc();
        $this->assertTrue($c->isOpen($this->moment('2026-06-22 09:00')));  // Mon
        $this->assertFalse($c->isOpen($this->moment('2026-06-22 07:30'))); // before open
        $this->assertFalse($c->isOpen($this->moment('2026-06-22 17:00'))); // at close (exclusive)
        $this->assertTrue($c->isOpen($this->moment('2026-06-27 12:00')));  // Sat noon
        $this->assertFalse($c->isOpen($this->moment('2026-06-27 14:00'))); // Sat after close
        $this->assertFalse($c->isOpen($this->moment('2026-06-28 10:00'))); // Sun closed
    }

    public function test_add_minutes_within_day(): void
    {
        $due = $this->calc()->addBusinessMinutes($this->moment('2026-06-22 09:00'), 60);
        $this->assertSame('2026-06-22 10:00', $due->format('Y-m-d H:i'));
    }

    public function test_add_minutes_starts_outside_hours(): void
    {
        // Mon 18:00 → next open Tue 08:00, +60 = Tue 09:00
        $due = $this->calc()->addBusinessMinutes($this->moment('2026-06-22 18:00'), 60);
        $this->assertSame('2026-06-23 09:00', $due->format('Y-m-d H:i'));
    }

    public function test_add_minutes_rolls_friday_into_saturday(): void
    {
        // Fri 16:30 (+30 to close) then 90 more → Sat 08:00 + 90 = Sat 09:30
        $due = $this->calc()->addBusinessMinutes($this->moment('2026-06-26 16:30'), 120);
        $this->assertSame('2026-06-27 09:30', $due->format('Y-m-d H:i'));
    }

    public function test_add_minutes_skips_sunday(): void
    {
        // Sat 12:30 (+30 to close) then 30 more → Sun closed → Mon 08:30
        $due = $this->calc()->addBusinessMinutes($this->moment('2026-06-27 12:30'), 60);
        $this->assertSame('2026-06-29 08:30', $due->format('Y-m-d H:i'));
    }

    public function test_low_resolution_five_business_days(): void
    {
        // 2700 business min from Mon 08:00 = 5 × 540 (Mon–Fri full) → Fri 17:00
        $due = $this->calc()->addBusinessMinutes($this->moment('2026-06-22 08:00'), 2700);
        $this->assertSame('2026-06-26 17:00', $due->format('Y-m-d H:i'));
    }

    public function test_holiday_is_skipped(): void
    {
        // Tue 2026-06-23 is a holiday → Mon 16:30 +60 rolls past Tue to Wed 08:30
        $c = $this->calc(['2026-06-23']);
        $due = $c->addBusinessMinutes($this->moment('2026-06-22 16:30'), 60);
        $this->assertSame('2026-06-24 08:30', $due->format('Y-m-d H:i'));
    }

    public function test_business_minutes_between_full_day(): void
    {
        $c = $this->calc();
        $this->assertSame(540, $c->businessMinutesBetween(
            $this->moment('2026-06-22 08:00'),
            $this->moment('2026-06-22 17:00'),
        ));
    }

    public function test_business_minutes_between_spans_closed_time(): void
    {
        // Mon 16:30 → Tue 09:00 = 30 (Mon) + 60 (Tue) = 90
        $c = $this->calc();
        $this->assertSame(90, $c->businessMinutesBetween(
            $this->moment('2026-06-22 16:30'),
            $this->moment('2026-06-23 09:00'),
        ));
    }

    public function test_business_minutes_between_ignores_sunday(): void
    {
        // Sat 12:00 → Mon 09:00 = 60 (Sat to 13:00) + 60 (Mon 08:00–09:00) = 120
        $c = $this->calc();
        $this->assertSame(120, $c->businessMinutesBetween(
            $this->moment('2026-06-27 12:00'),
            $this->moment('2026-06-29 09:00'),
        ));
    }
}
