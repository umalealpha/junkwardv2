<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebfleetObject extends Model
{
    use HasFactory;

    protected $table = 'webfleet_objects';

    protected $fillable = [
        'objectno',
        'objectname',
        'objecttype',
        'drivername',
        'odometer_long',
        'raw_payload',
    ];

    protected $casts = [
        'odometer_long' => 'integer',
        'raw_payload' => 'array',
    ];

    /**
     * Get all trips for this object.
     */
    public function trips()
    {
        return $this->hasMany(Trip::class, 'objectno', 'objectno');
    }

    /**
     * Get all trip summaries for this object.
     */
    public function tripSummaries()
    {
        return $this->hasMany(TripSummary::class, 'objectno', 'objectno');
    }
}

