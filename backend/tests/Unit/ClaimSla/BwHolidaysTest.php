<?php

namespace Tests\Unit\ClaimSla;

use AlphaDirect\Console\Commands\ClaimsSeedBwHolidays;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB. Pins the Botswana public-holiday computation (ported
 * from the legacy Claims Tracker) against known 2026/2027 dates so the claims
 * SLA calendar excludes exactly the days the tracker did.
 */
class BwHolidaysTest extends TestCase
{
    private function cmd(): ClaimsSeedBwHolidays
    {
        return new ClaimsSeedBwHolidays();
    }

    public function test_easter_sunday_is_correct(): void
    {
        $this->assertSame('2026-04-05', $this->cmd()->easterSunday(2026)->toDateString());
        $this->assertSame('2027-03-28', $this->cmd()->easterSunday(2027)->toDateString());
    }

    public function test_2026_holidays_match_known_dates(): void
    {
        $h = $this->cmd()->holidaysForYear(2026);

        // Fixed days.
        $this->assertArrayHasKey('2026-01-01', $h); // New Year
        $this->assertArrayHasKey('2026-01-02', $h);
        $this->assertArrayHasKey('2026-05-01', $h); // Labour Day
        $this->assertArrayHasKey('2026-07-01', $h); // Sir Seretse Khama Day
        $this->assertArrayHasKey('2026-09-30', $h); // Botswana Day
        $this->assertArrayHasKey('2026-10-01', $h);
        $this->assertArrayHasKey('2026-12-25', $h); // Christmas
        $this->assertArrayHasKey('2026-12-26', $h); // Boxing Day

        // President's Day — 3rd Monday of July 2026 = 20 Jul, + next day.
        $this->assertArrayHasKey('2026-07-20', $h);
        $this->assertArrayHasKey('2026-07-21', $h);

        // Easter-based (Easter Sunday 2026 = 5 Apr).
        $this->assertArrayHasKey('2026-04-03', $h); // Good Friday
        $this->assertArrayHasKey('2026-04-04', $h); // Holy Saturday
        $this->assertArrayHasKey('2026-04-06', $h); // Easter Monday
        $this->assertArrayHasKey('2026-05-14', $h); // Ascension (Easter + 39)

        // 8 fixed + 2 President's + 4 Easter-based = 14 distinct days.
        $this->assertCount(14, $h);
    }

    public function test_names_are_populated(): void
    {
        $h = $this->cmd()->holidaysForYear(2026);
        $this->assertSame("New Year's Day", $h['2026-01-01']);
        $this->assertSame('Good Friday', $h['2026-04-03']);
        $this->assertSame("President's Day", $h['2026-07-20']);
    }
}
