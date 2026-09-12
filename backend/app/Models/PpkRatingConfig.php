<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PpkRatingConfig extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ppk_rating_config';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Base Pricing
        'base_monthly_premium',
        'base_rate_per_km',
        
        // Distance Range
        'distance_range_min',
        'distance_range_max',
        
        // Per-trip caps
        'per_trip_cap_min',
        'per_trip_cap_max',
        
        // Monthly caps
        'monthly_cap_min',
        'monthly_cap_max',
        
        // Risk Modifiers
        'night_modifier',
        'rain_modifier',
        
        // JSON modifiers
        'optidrive_modifiers',
        'driver_age_modifiers',
        'car_age_modifiers',
        'vehicle_type_modifiers',
        'driver_risk_modifiers',
        'territory_modifiers',
        'trip_type_modifiers',
        'usage_frequency_modifiers',
        'policy_tenure_modifiers',
        'claims_history_modifiers',
        
        // Gender factor
        'gender_enabled',
        'gender_modifiers',
        
        // Event penalty rates
        'speeding_penalty_per_event',
        'harsh_acceleration_rate',
        'idle_time_rate',
        'high_revving_rate',
        
        // Weather and night modifiers
        'weather_severity_modifiers',
        'night_segment_modifiers',
        
        // Other modifiers
        'congestion_modifier',
        'seasonal_modifier',
        'adas_discount',
        'roadworthiness_discount',
        'safe_driver_cashback',
        
        // Metadata
        'config_name',
        'description',
        'is_active',
        'section_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_monthly_premium' => 'decimal:2',
        'base_rate_per_km' => 'decimal:2',
        'distance_range_min' => 'integer',
        'distance_range_max' => 'integer',
        'per_trip_cap_min' => 'decimal:2',
        'per_trip_cap_max' => 'decimal:2',
        'monthly_cap_min' => 'decimal:2',
        'monthly_cap_max' => 'decimal:2',
        'night_modifier' => 'decimal:4',
        'rain_modifier' => 'decimal:4',
        'optidrive_modifiers' => 'array',
        'driver_age_modifiers' => 'array',
        'car_age_modifiers' => 'array',
        'vehicle_type_modifiers' => 'array',
        'driver_risk_modifiers' => 'array',
        'territory_modifiers' => 'array',
        'trip_type_modifiers' => 'array',
        'usage_frequency_modifiers' => 'array',
        'policy_tenure_modifiers' => 'array',
        'claims_history_modifiers' => 'array',
        'gender_enabled' => 'boolean',
        'gender_modifiers' => 'array',
        'speeding_penalty_per_event' => 'decimal:2',
        'harsh_acceleration_rate' => 'decimal:2',
        'idle_time_rate' => 'decimal:2',
        'high_revving_rate' => 'decimal:2',
        'weather_severity_modifiers' => 'array',
        'night_segment_modifiers' => 'array',
        'congestion_modifier' => 'decimal:4',
        'seasonal_modifier' => 'decimal:4',
        'adas_discount' => 'decimal:4',
        'roadworthiness_discount' => 'decimal:4',
        'safe_driver_cashback' => 'decimal:4',
        'is_active' => 'boolean',
        'section_enabled' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the default configuration values.
     *
     * @return array
     */
    public static function getDefaultConfig(): array
    {
        return [
            'base_monthly_premium' => 125.0,
            'base_rate_per_km' => 0.3,
            'per_trip_cap_min' => 0.0,
            'per_trip_cap_max' => 50.0,
            'monthly_cap_min' => 80.0,
            'monthly_cap_max' => 900.0,
            'night_modifier' => 0.08,
            'rain_modifier' => 0.10,
            'optidrive_modifiers' => [
                'category_a' => ['value' => 0.0, 'enabled' => true],   // 0.8 to 1.0 - Surcharge: 0%
                'category_b' => ['value' => 0.20, 'enabled' => true],  // 0.6 to <0.8 - Surcharge: 20%
                'category_c' => ['value' => 0.40, 'enabled' => true],  // 0.4 to <0.6 - Surcharge: 40%
                'category_d' => ['value' => 0.60, 'enabled' => true]   // <0.4 - Surcharge: 60%
            ],
            'driver_age_modifiers' => [
                'young' => 0.10,
                'adult' => 0.0,
                'senior' => 0.05,
                'elderly' => 0.15
            ],
            'car_age_modifiers' => [
                'new' => 0.0,
                'mid' => 0.03,
                'old' => 0.06,
                'very_old' => 0.08
            ],
            'vehicle_type_modifiers' => [
                'sedan' => 0.0,
                'suv' => 0.05,
                'pickup' => 0.07,
                'ev' => -0.03
            ],
            'driver_risk_modifiers' => [
                'low' => 0.0,
                'medium' => 0.05,
                'high' => 0.10
            ],
            'territory_modifiers' => [
                'urban' => 0.05,
                'rural' => 0.0,
                'offroad' => 0.10
            ],
            'trip_type_modifiers' => [
                'commute' => 0.0,
                'long_trip' => 0.02,
                'offroad' => 0.08
            ],
            'usage_frequency_modifiers' => [
                'lt_500' => -0.05,
                'btw_500_1000' => 0.0,
                'gt_1000' => 0.05
            ],
            'policy_tenure_modifiers' => [
                'lt_2y' => 0.0,
                'gt_2y' => -0.03,
                'gt_5y' => -0.05
            ],
            'claims_history_modifiers' => [
                'c0' => 0.0,
                'c1' => 0.03,
                'c2' => 0.06,
                'c3_plus' => 0.10
            ],
            'gender_enabled' => false,
            'gender_modifiers' => [
                'M' => 0.02,
                'F' => 0.0,
                'X' => 0.0
            ],
            'speeding_penalty_per_event' => 0.10,
            'harsh_acceleration_rate' => 0.05,
            'idle_time_rate' => 0.02,
            'high_revving_rate' => 0.03,
            'weather_severity_modifiers' => [
                'drizzle' => 0.05,
                'rain' => 0.10,
                'heavy_rain' => 0.15,
                'thunderstorm' => 0.15
            ],
            'night_segment_modifiers' => [
                'evening' => 0.05,
                'late_night' => 0.10
            ],
            'congestion_modifier' => 0.05,
            'seasonal_modifier' => 0.05,
            'adas_discount' => -0.05,
            'roadworthiness_discount' => -0.02,
            'safe_driver_cashback' => -0.03,
            'section_enabled' => [
                'distance_range_config' => true,
                'caps_config' => true,
                'basic_risk_modifiers' => true,
                'optidrive_modifiers' => true,
                'driver_age_modifiers' => true,
                'car_age_modifiers' => true,
                'vehicle_type_modifiers' => true,
                'driver_risk_modifiers' => true,
                'territory_modifiers' => true,
                'trip_type_modifiers' => true,
                'usage_frequency_modifiers' => true,
                'policy_tenure_modifiers' => true,
                'claims_history_modifiers' => true,
                'event_penalty_rates' => true,
                'weather_severity_modifiers' => true,
                'night_segment_modifiers' => true,
                'other_modifiers' => true,
            ],
        ];
    }

    /**
     * Get the active configuration.
     *
     * @return PpkRatingConfig|null
     */
    public static function getActiveConfig()
    {
        return self::where('is_active', true)->first();
    }
}

