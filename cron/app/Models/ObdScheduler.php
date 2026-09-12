<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObdScheduler extends Model
{
    use HasFactory;

    protected $table = 'obd_schedulers';

    protected $fillable = [
        'policy_id',
        'policy_number',
        'vehicle_plate',
        'year',
        'month',
        'distance_km',
        'rate_per_km',
        'calculated_premium',
        'calculation_details',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'distance_km' => 'decimal:2',
        'rate_per_km' => 'decimal:2',
        'calculated_premium' => 'decimal:2',
        'calculation_details' => 'array',
    ];

    /**
     * Get the policy associated with this OBD scheduler entry.
     */
    public function policy()
    {
        return $this->belongsTo('AlphaDirect\Policy', 'policy_id', 'id');
    }

    /**
     * Get the vehicle associated with this OBD scheduler entry.
     */
    public function vehicle()
    {
        return $this->belongsTo('AlphaDirect\Vehicle', 'vehicle_plate', 'vehiclePlate');
    }

    /**
     * Scope to get records for a specific month and year.
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /**
     * Scope to get records for a specific policy.
     */
    public function scopeForPolicy($query, $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    /**
     * Calculate premium based on distance and rate.
     */
    public static function calculatePremium($distanceKm, $ratePerKm = 2.00)
    {
        return round($distanceKm * $ratePerKm, 2);
    }
}

