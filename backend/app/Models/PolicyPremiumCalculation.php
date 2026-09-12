<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Policy;
use AlphaDirect\Vehicle;
use AlphaDirect\User;

class PolicyPremiumCalculation extends Model
{
    use HasFactory;

    protected $table = 'policy_premium_calculations';

    protected $fillable = [
        'policy_id',
        'policy_number',
        'vehicle_id',
        'vehicle_plate',
        'product_id',
        'start_date',
        'end_date',
        'days_count',
        'trips_count',
        'actual_distance_km',
        'billable_distance_km',
        'distance_range_min',
        'distance_range_max',
        'distance_adjustment',
        'behaviour_category',
        'average_optidrive',
        'behaviour_surcharge_percentage',
        'behaviour_category_auto_calculated',
        'monthly_premium',
        'premium_source',
        'base_rate_per_km',
        'final_rate_per_km',
        'base_premium',
        'surcharge_amount',
        'total_premium',
        'calculation_breakdown',
        'calculated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_distance_km' => 'decimal:2',
        'billable_distance_km' => 'decimal:2',
        'average_optidrive' => 'decimal:4',
        'behaviour_surcharge_percentage' => 'decimal:2',
        'behaviour_category_auto_calculated' => 'boolean',
        'monthly_premium' => 'decimal:2',
        'base_rate_per_km' => 'decimal:4',
        'final_rate_per_km' => 'decimal:4',
        'base_premium' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'total_premium' => 'decimal:2',
        'calculation_breakdown' => 'json',
    ];

    // Relationships
    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    // Scopes
    public function scopeForPolicy($query, $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    public function scopeForPolicyNumber($query, $policyNumber)
    {
        return $query->where('policy_number', $policyNumber);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_date', '>=', $startDate)
                    ->where('end_date', '<=', $endDate);
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
