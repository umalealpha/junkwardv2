<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripSummary extends Model
{
    use HasFactory;

    protected $table = 'trip_summaries';

    protected $fillable = [
        'objectno',
        'for_date',
        'distance_m',
        'duration_s',
        'trips',
        'raw_payload',
    ];

    protected $casts = [
        'for_date' => 'date',
        'distance_m' => 'integer',
        'duration_s' => 'integer',
        'trips' => 'integer',
        'raw_payload' => 'array',
    ];

    /**
     * Get the webfleet object associated with this summary.
     */
    public function webfleetObject()
    {
        return $this->belongsTo(WebfleetObject::class, 'objectno', 'objectno');
    }

    /**
     * Get the trip records for this summary date.
     */
    public function tripRecords()
    {
        return $this->hasMany(Trip::class, 'objectno', 'objectno')
            ->whereDate('start_time', $this->for_date);
    }
}

