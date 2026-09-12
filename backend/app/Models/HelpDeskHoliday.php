<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A public holiday — treated as a fully-closed day by the SLA business-hours
 * engine. Seeded empty; rows can be added without any code change.
 */
class HelpDeskHoliday extends Model
{
    protected $table = 'help_desk_holidays';

    protected $fillable = ['holiday_date', 'name', 'recurring'];

    protected $casts = [
        'holiday_date' => 'date',
        'recurring'    => 'boolean',
    ];
}
