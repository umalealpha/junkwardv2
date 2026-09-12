<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per ISO day-of-week (1=Mon … 7=Sun) describing the business window
 * for that day. Consumed by BusinessCalendarRepository to build the
 * BusinessHoursCalculator.
 */
class HelpDeskBusinessCalendar extends Model
{
    protected $table = 'help_desk_business_calendar';

    protected $fillable = ['day_of_week', 'open_time', 'close_time', 'is_open'];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_open'     => 'boolean',
    ];
}
