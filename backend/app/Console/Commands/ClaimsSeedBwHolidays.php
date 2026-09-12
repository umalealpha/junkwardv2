<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\HelpDeskHoliday;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Seed Botswana public holidays into `help_desk_holidays`, which the claims SLA
 * engine (ClaimSlaCalendar) reads to exclude closed days from the working-day
 * clock. Ported 1:1 from the legacy Claims Tracker's getBotswanaHolidays()
 * (D:\ADRisk\claims\js\data.js) — the same list Bharath validated — so Graphite's
 * SLA calendar matches the tracker exactly.
 *
 * Fixed days: New Year (1-2 Jan), Labour Day (1 May), Sir Seretse Khama Day
 * (1 Jul), Botswana Day (30 Sep + 1 Oct), Christmas + Boxing Day (25-26 Dec).
 * President's Day: 3rd Monday of July + the following day.
 * Easter-based (computus): Good Friday, Holy Saturday, Easter Monday, Ascension.
 *
 * SAFE / INERT: idempotent (firstOrCreate on the unique holiday_date), and
 * DRY-RUN by default — prints what it would add and writes nothing unless
 * --apply is passed. Reference data only; no claim/financial tables touched.
 *
 * NOTE: help_desk_holidays is shared with the Help Desk SLA engine, but that
 * engine is retired/unscheduled (Alpha Bridge owns Help Desk) — so seeding it
 * affects only the claims SLA calendar in practice. Running it DOES shift live
 * claims SLA due dates (claims_sla is on), so run it deliberately, bundled with
 * the rest of the SLA correction, in a safe window.
 */
class ClaimsSeedBwHolidays extends Command
{
    protected $signature = 'claims:seed-bw-holidays
        {--from= : First year to seed (default: last year)}
        {--to= : Last year to seed (default: next year)}
        {--apply : Actually write rows (default is a dry run)}';

    protected $description = 'Seed Botswana public holidays for the claims SLA calendar (dry-run unless --apply).';

    public function handle(): int
    {
        $from = (int) ($this->option('from') ?: (now()->year - 1));
        $to   = (int) ($this->option('to') ?: (now()->year + 1));
        $apply = (bool) $this->option('apply');

        if ($to < $from) {
            $this->error("--to ($to) is before --from ($from).");
            return self::FAILURE;
        }

        $rows = [];
        for ($year = $from; $year <= $to; $year++) {
            foreach ($this->holidaysForYear($year) as $date => $name) {
                $rows[$date] = $name;
            }
        }
        ksort($rows);

        $created = 0;
        $existing = 0;
        $display = [];
        foreach ($rows as $date => $name) {
            $exists = HelpDeskHoliday::whereDate('holiday_date', $date)->exists();
            if ($exists) {
                $existing++;
                $display[] = [$date, $name, 'exists'];
                continue;
            }
            if ($apply) {
                HelpDeskHoliday::create(['holiday_date' => $date, 'name' => $name, 'recurring' => false]);
            }
            $created++;
            $display[] = [$date, $name, $apply ? 'created' : 'would add'];
        }

        $this->table(['Date', 'Holiday', 'Action'], $display);
        $this->info(sprintf(
            '%s — years %d–%d: %d already present, %d %s.',
            $apply ? 'APPLIED' : 'DRY RUN (no rows written; pass --apply to write)',
            $from, $to, $existing, $created, $apply ? 'created' : 'to add'
        ));

        return self::SUCCESS;
    }

    /**
     * Botswana public holidays for one year, as ['Y-m-d' => name].
     * Ported from the legacy tracker's getBotswanaHolidays().
     *
     * @return array<string,string>
     */
    public function holidaysForYear(int $year): array
    {
        $out = [];
        $add = function (Carbon $d, string $name) use (&$out) {
            $out[$d->toDateString()] = $name;
        };

        // Fixed-date holidays.
        $add(Carbon::create($year, 1, 1), "New Year's Day");
        $add(Carbon::create($year, 1, 2), 'New Year Holiday');
        $add(Carbon::create($year, 5, 1), 'Labour Day');
        $add(Carbon::create($year, 7, 1), 'Sir Seretse Khama Day');
        $add(Carbon::create($year, 9, 30), 'Botswana Day');
        $add(Carbon::create($year, 10, 1), 'Botswana Day Holiday');
        $add(Carbon::create($year, 12, 25), 'Christmas Day');
        $add(Carbon::create($year, 12, 26), 'Boxing Day');

        // President's Day — 3rd Monday of July, plus the following day.
        $presidents = Carbon::create($year, 7, 1)->nthOfMonth(3, Carbon::MONDAY);
        $add($presidents->copy(), "President's Day");
        $add($presidents->copy()->addDay(), "President's Day Holiday");

        // Easter-based holidays (computus — same algorithm as the legacy tracker).
        $easter = $this->easterSunday($year);
        $add($easter->copy()->subDays(2), 'Good Friday');
        $add($easter->copy()->subDay(), 'Holy Saturday');
        $add($easter->copy()->addDay(), 'Easter Monday');
        $add($easter->copy()->addDays(39), 'Ascension Day');

        return $out;
    }

    /** Easter Sunday for a given year (Anonymous Gregorian / computus algorithm). */
    public function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31); // 3 = March, 4 = April
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}
