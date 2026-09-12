<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $table = 'trips';

    protected $fillable = [
        'ext_trip_id',
        'objectno',
        'objectname',
        'objectuid',
        'tripmode',
        'start_time',
        'end_time',
        'duration_s',
        'idle_time',
        'start_lat',
        'start_lon',
        'end_lat',
        'end_lon',
        'start_postext',
        'end_postext',
        'start_odometer',
        'end_odometer',
        'distance_m',
        'avg_speed',
        'max_speed',
        'driverno',
        'drivername',
        'driveruid',
        'fueltype',
        'optidrive_indicator',
        'speeding_indicator',
        'drivingevents_indicator',
        'idling_indicator',
        'constant_speed_indicator',
        'high_revving_indicator',
        'raw_payload',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_s' => 'integer',
        'idle_time' => 'integer',
        'start_lat' => 'integer',
        'start_lon' => 'integer',
        'end_lat' => 'integer',
        'end_lon' => 'integer',
        'start_odometer' => 'integer',
        'end_odometer' => 'integer',
        'distance_m' => 'integer',
        'avg_speed' => 'integer',
        'max_speed' => 'integer',
        'fueltype' => 'integer',
        'optidrive_indicator' => 'decimal:3',
        'speeding_indicator' => 'decimal:3',
        'drivingevents_indicator' => 'decimal:3',
        'idling_indicator' => 'decimal:3',
        'constant_speed_indicator' => 'decimal:3',
        'high_revving_indicator' => 'decimal:3',
        'raw_payload' => 'array',
    ];

    /**
     * Get the webfleet object associated with this trip.
     */
    public function webfleetObject()
    {
        return $this->belongsTo(WebfleetObject::class, 'objectno', 'objectno');
    }

    /**
     * Get the trip summary for this trip's date.
     */
    public function tripSummary()
    {
        return $this->hasOne(TripSummary::class, 'objectno', 'objectno')
            ->where('for_date', $this->start_time->toDateString());
    }
}

