<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\HelpDeskBusinessCalendar;
use AlphaDirect\Models\HelpDeskHoliday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds a BusinessHoursCalculator from the DB-backed weekly calendar +
 * holiday tables. The assembled schedule is cached (changes are rare) and can
 * be flushed when the calendar is edited.
 */
class BusinessCalendarRepository
{
    private const CACHE_KEY = 'help_desk.business_calendar';

    public function calculator(): BusinessHoursCalculator
    {
        $ttl = (int) config('help_desk.sla.calendar_cache_minutes', 60);
        $tz  = (string) config('help_desk.timezone', 'Africa/Gaborone');

        [$schedule, $holidays] = Cache::remember(self::CACHE_KEY, now()->addMinutes($ttl), function () {
            $schedule = [];
            foreach (HelpDeskBusinessCalendar::all() as $row) {
                if ($row->is_open && $row->open_time && $row->close_time) {
                    $schedule[(int) $row->day_of_week] = [
                        'open'  => (string) $row->open_time,
                        'close' => (string) $row->close_time,
                    ];
                }
            }
            $holidays = HelpDeskHoliday::pluck('holiday_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all();

            return [$schedule, $holidays];
        });

        return new BusinessHoursCalculator($schedule, $holidays, $tz);
    }

    /** Bust the cache after the calendar or holidays change. */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
