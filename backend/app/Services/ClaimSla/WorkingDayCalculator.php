<?php

namespace AlphaDirect\Services\ClaimSla;

use Carbon\Carbon;

/**
 * Pure WORKING-DAY arithmetic for the claims SLA engine.
 *
 * The claims SLA unit is the working DAY (unlike the Help Desk SLA engine,
 * which reckons in business MINUTES). Each calendar day carries a WEIGHT — the
 * fraction of a working day it counts as:
 *   - a full working day weighs 1.0,
 *   - a HALF day (e.g. Saturday for the claims team) weighs 0.5,
 *   - a closed day / public holiday weighs 0.0 (does not count at all).
 *
 * A deadline of N working days is therefore "advance from the anchor, summing
 * daily weights, until the running total reaches N" — so a Mon–Fri + half-Sat
 * week consumes 5.5 working days of deadline per calendar week.
 *
 * Deliberately dependency-free (open-days + weights + holidays injected) so it
 * is fully unit-testable without a database. ClaimSlaCalendar builds the
 * DB-backed instance in production, reusing the Help Desk business-calendar +
 * holiday tables so both engines observe the same closed days.
 *
 *   $openDaysIso : list of ISO weekday numbers that are working days
 *                  (1 = Mon ... 7 = Sun). Default Mon–Fri.
 *   $holidays    : ['Y-m-d', ...] — each treated as a fully non-working day.
 *   $dayWeights  : [isoWeekday => float] — fractional weight for an open day.
 *                  Any open day omitted defaults to a full day (1.0). This is
 *                  how Saturday becomes a half day WITHOUT affecting the shared
 *                  Help Desk calendar (which only knows open/closed).
 *
 * All reckoning is done on the calendar DATE only (time-of-day is ignored):
 * SLA stage dates in claim_tracker_workflow are `date` casts.
 *
 * Backward compatibility: with every open day left at the default weight 1.0,
 * this behaves identically to the previous integer working-day calculator.
 */
class WorkingDayCalculator
{
    /** Floating-point comparison tolerance for weight accumulation. */
    private const EPS = 1e-9;

    /** @var array<int,float> isoWeekday => weight (>0 means it is a working day) */
    private array $dayWeights;

    /** @var array<int,string> set of 'Y-m-d' holiday dates */
    private array $holidays;

    private string $timezone;

    /**
     * @param int[]              $openDaysIso
     * @param string[]           $holidays
     * @param array<int,float>   $dayWeights  isoWeekday => fractional weight (open days only)
     */
    public function __construct(
        array $openDaysIso = [1, 2, 3, 4, 5],
        array $holidays = [],
        string $timezone = 'Africa/Gaborone',
        array $dayWeights = []
    ) {
        $this->holidays = array_values(array_unique($holidays));
        $this->timezone = $timezone;

        // Build the weight map from the open days: default 1.0 per open day, with
        // any explicit override applied (clamped to >= 0). Days not open weigh 0.
        $this->dayWeights = [];
        foreach (array_unique(array_map('intval', $openDaysIso)) as $iso) {
            $w = array_key_exists($iso, $dayWeights) ? (float) $dayWeights[$iso] : 1.0;
            $this->dayWeights[$iso] = max(0.0, $w);
        }
    }

    /**
     * The fraction of a working day $date counts as: 0.0 for holidays / closed
     * days, otherwise the configured weight for that weekday (default 1.0).
     */
    public function weightForDate(Carbon $date): float
    {
        $d = $date->copy()->setTimezone($this->timezone);
        if (in_array($d->toDateString(), $this->holidays, true)) {
            return 0.0;
        }
        return $this->dayWeights[$d->dayOfWeekIso] ?? 0.0;
    }

    /** Is $date a working day at all (weight > 0 — i.e. open, not a holiday)? */
    public function isWorkingDay(Carbon $date): bool
    {
        return $this->weightForDate($date) > 0.0;
    }

    /**
     * The date itself if it is a working day, otherwise the next working day
     * after it. Normalised to midnight in the calendar timezone.
     */
    public function nextWorkingDay(Carbon $date): Carbon
    {
        $cursor = $date->copy()->setTimezone($this->timezone)->startOfDay();
        for ($i = 0; $i < 800; $i++) {
            if ($this->isWorkingDay($cursor)) {
                return $cursor->copy();
            }
            $cursor->addDay();
        }
        throw new \RuntimeException('No working day found within 800 days — check the business calendar.');
    }

    /**
     * Add $n WORKING days to $start and return the resulting due date
     * (midnight, calendar timezone).
     *
     * Convention: the anchor day (the SLA start) is working-day 0. Adding N
     * working days lands on the first calendar day AFTER the anchor's working
     * day on which the cumulative day-weight reaches N. n <= 0 returns the
     * start's working day.
     *
     * Example (Mon start, Mon–Fri full weeks, no holidays): +2 => Wed; a Fri
     * start +2 => following Tue (weekend skipped). With a half-weight Saturday,
     * a Thu start +2 => Mon (Fri=1.0, Sat=0.5, Mon reaches 2.0).
     */
    public function addWorkingDays(Carbon $start, float $n): Carbon
    {
        $cursor = $this->nextWorkingDay($start);
        if ($n <= 0) {
            return $cursor;
        }
        $acc = 0.0;
        for ($guard = 0; $guard < 100000; $guard++) {
            $cursor->addDay();
            $acc += $this->weightForDate($cursor);
            if ($acc + self::EPS >= $n) {
                return $cursor;
            }
        }
        return $cursor;
    }

    /**
     * Sum of day-WEIGHTS strictly after $from up to and including $to.
     * Zero if $to is on/before $from's date. Approximately symmetric to
     * addWorkingDays: workingDaysBetween($start, addWorkingDays($start, n)) >= n
     * (exactly n on full-day weeks; may slightly exceed n when the deadline is
     * reached on a fractional-weight day).
     */
    public function workingDaysBetween(Carbon $from, Carbon $to): float
    {
        $a = $from->copy()->setTimezone($this->timezone)->startOfDay();
        $b = $to->copy()->setTimezone($this->timezone)->startOfDay();
        if ($b->lte($a)) {
            return 0.0;
        }
        $sum    = 0.0;
        $cursor = $a->copy();
        for ($guard = 0; $cursor->lt($b) && $guard < 100000; $guard++) {
            $cursor->addDay();
            $sum += $this->weightForDate($cursor);
        }
        return $sum;
    }
}
