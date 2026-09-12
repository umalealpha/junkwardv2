<?php

namespace AlphaDirect\Services\ClaimSla;

use AlphaDirect\Models\HelpDeskBusinessCalendar;
use AlphaDirect\Models\HelpDeskHoliday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds a WorkingDayCalculator from the SAME DB-backed weekly calendar +
 * holiday tables the Help Desk SLA engine uses, so both engines observe an
 * identical set of closed days / public holidays. Mirrors
 * BusinessCalendarRepository (the Help Desk equivalent) but yields the
 * working-DAY calculator the claims engine needs.
 *
 * Resilient by design: if the Help Desk calendar tables are empty or
 * unreadable, it falls back to a Mon–Fri working week with no holidays, so the
 * claims SLA engine never hard-depends on Help Desk being configured.
 */
class ClaimSlaCalendar
{
    private const CACHE_KEY = 'claims_sla.working_calendar';

    public function calculator(): WorkingDayCalculator
    {
        $ttl = (int) config('help_desk.sla.calendar_cache_minutes', 60);
        $tz  = (string) config('claims_sla.timezone', 'Africa/Gaborone');

        [$openDays, $holidays] = Cache::remember(self::CACHE_KEY, now()->addMinutes($ttl), function () {
            $openDays = [];
            $holidays = [];
            try {
                foreach (HelpDeskBusinessCalendar::all() as $row) {
                    if ($row->is_open) {
                        $openDays[] = (int) $row->day_of_week;
                    }
                }
                $holidays = HelpDeskHoliday::pluck('holiday_date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->all();
            } catch (\Throwable $e) {
                // Fall through to defaults below.
            }

            if (empty($openDays)) {
                $openDays = [1, 2, 3, 4, 5]; // Mon–Fri default working week
            }

            return [array_values(array_unique($openDays)), $holidays];
        });

        // Per-weekday fractional weights (e.g. Saturday = half day) layered on
        // top of the shared open/closed calendar. Env-gated, defaults to full.
        $dayWeights = (array) config('claims_sla.day_weights', []);

        return new WorkingDayCalculator($openDays, $holidays, $tz, $dayWeights);
    }

    /** Bust the cache after the shared calendar or holidays change. */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
