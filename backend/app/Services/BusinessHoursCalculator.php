<?php

namespace AlphaDirect\Services;

use Carbon\Carbon;

/**
 * Pure business-hours arithmetic for the Help Desk SLA engine. Counts time
 * ONLY during configured business windows, skipping closed days and holidays.
 *
 * Deliberately dependency-free (schedule + holidays injected) so it is fully
 * unit-testable without a database. BusinessCalendarRepository builds the
 * DB-backed instance in production.
 *
 *   $schedule : [ isoDay(1=Mon..7=Sun) => ['open' => 'HH:MM', 'close' => 'HH:MM'], ... ]
 *               Closed days are simply absent.
 *   $holidays : ['Y-m-d', ...] — each treated as a fully-closed day.
 *
 * All inputs are normalised to $timezone before any day/time reckoning, and
 * results are returned in $timezone (the caller may convert as needed).
 */
class BusinessHoursCalculator
{
    /** @var array<int, array{open:string, close:string}> */
    private array $schedule;

    /** @var array<int, string> set of 'Y-m-d' holiday dates */
    private array $holidays;

    private string $timezone;

    public function __construct(array $schedule, array $holidays = [], string $timezone = 'Africa/Gaborone')
    {
        $this->schedule = $schedule;
        $this->holidays = array_values($holidays);
        $this->timezone = $timezone;
    }

    /** Is the given instant inside an open business window? */
    public function isOpen(Carbon $dt): bool
    {
        $dt = $dt->copy()->setTimezone($this->timezone);
        $w = $this->windowFor($dt);
        return $w !== null && $dt->gte($w[0]) && $dt->lt($w[1]);
    }

    /**
     * The first business instant at or after $dt. If $dt is already inside a
     * window, $dt itself is returned (normalised to the calendar timezone).
     */
    public function nextOpen(Carbon $dt): Carbon
    {
        $cursor = $dt->copy()->setTimezone($this->timezone);
        for ($i = 0; $i < 800; $i++) {
            $w = $this->windowFor($cursor);
            if ($w !== null) {
                [$open, $close] = $w;
                if ($cursor->lt($open)) {
                    return $open->copy();
                }
                if ($cursor->lt($close)) {
                    return $cursor->copy();
                }
            }
            $cursor = $cursor->copy()->addDay()->startOfDay();
        }
        throw new \RuntimeException('No business hours configured within 800 days — check the business calendar.');
    }

    /**
     * Project a deadline $businessMinutes of working time after $start,
     * skipping all non-business time. Returns $start (normalised) for a
     * non-positive duration.
     */
    public function addBusinessMinutes(Carbon $start, int $businessMinutes): Carbon
    {
        if ($businessMinutes <= 0) {
            return $start->copy()->setTimezone($this->timezone);
        }

        $cursor    = $this->nextOpen($start);
        $remaining = $businessMinutes;

        for ($guard = 0; $remaining > 0 && $guard < 100000; $guard++) {
            [$open, $close] = $this->windowFor($cursor); // cursor is open here (nextOpen)
            $avail = (int) $cursor->diffInMinutes($close);

            if ($avail >= $remaining) {
                return $cursor->copy()->addMinutes($remaining);
            }
            $remaining -= $avail;
            $cursor = $this->nextOpen($close->copy()); // close is not "open" → next window
        }

        return $cursor->copy();
    }

    /**
     * Business minutes elapsed between two instants (overlap of [$from, $to]
     * with open windows). Zero if $to <= $from.
     */
    public function businessMinutesBetween(Carbon $from, Carbon $to): int
    {
        $from = $from->copy()->setTimezone($this->timezone);
        $to   = $to->copy()->setTimezone($this->timezone);
        if ($to->lte($from)) {
            return 0;
        }

        $total  = 0;
        $cursor = $from->copy();

        for ($guard = 0; $cursor->lt($to) && $guard < 100000; $guard++) {
            if (!$this->isOpen($cursor)) {
                $cursor = $this->nextOpen($cursor);
                if ($cursor->gte($to)) {
                    break;
                }
            }
            [$open, $close] = $this->windowFor($cursor);
            $segmentEnd = $close->lt($to) ? $close->copy() : $to->copy();
            $total += (int) $cursor->diffInMinutes($segmentEnd);

            if ($segmentEnd->equalTo($close)) {
                $cursor = $this->nextOpen($close->copy());
            } else {
                $cursor = $segmentEnd; // == $to → loop ends
            }
        }

        return $total;
    }

    /**
     * The open/close Carbon pair for the calendar date of $date, or null if
     * that day is closed (no window, or a holiday).
     *
     * @return array{0:Carbon,1:Carbon}|null
     */
    private function windowFor(Carbon $date): ?array
    {
        if (in_array($date->toDateString(), $this->holidays, true)) {
            return null;
        }
        $w = $this->schedule[$date->dayOfWeekIso] ?? null;
        if (!$w || empty($w['open']) || empty($w['close'])) {
            return null;
        }
        $open  = $date->copy()->setTimeFromTimeString($w['open']);
        $close = $date->copy()->setTimeFromTimeString($w['close']);
        if ($close->lte($open)) {
            return null;
        }
        return [$open, $close];
    }
}
