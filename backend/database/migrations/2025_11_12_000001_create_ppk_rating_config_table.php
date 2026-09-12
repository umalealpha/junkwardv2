<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ppk_rating_config', function (Blueprint $table) {
            $table->id();
            
            // Base Pricing
            $table->decimal('base_monthly_premium', 10, 2)->default(125.0)->comment('BWP');
            $table->decimal('base_rate_per_km', 10, 2)->default(0.3)->comment('BWP/km');
            
            // Per-trip caps [min, max]
            $table->decimal('per_trip_cap_min', 10, 2)->default(0.0)->comment('BWP');
            $table->decimal('per_trip_cap_max', 10, 2)->default(50.0)->comment('BWP');
            
            // Monthly caps [min, max]
            $table->decimal('monthly_cap_min', 10, 2)->default(80.0)->comment('BWP');
            $table->decimal('monthly_cap_max', 10, 2)->default(900.0)->comment('BWP');
            
            // Risk Modifiers (stored as percentages in decimal form)
            $table->decimal('night_modifier', 5, 4)->default(0.08)->comment('+8%');
            $table->decimal('rain_modifier', 5, 4)->default(0.10)->comment('+10%');
            
            // OptiDrive score modifiers (JSON)
            $table->json('optidrive_modifiers')->nullable()->comment('poor, average, good');
            
            // Driver age modifiers (JSON)
            $table->json('driver_age_modifiers')->nullable()->comment('young, adult, senior, elderly');
            
            // Car age modifiers (JSON)
            $table->json('car_age_modifiers')->nullable()->comment('new, mid, old, very_old');
            
            // Vehicle type modifiers (JSON)
            $table->json('vehicle_type_modifiers')->nullable()->comment('sedan, suv, pickup, ev');
            
            // Driver risk tier modifiers (JSON)
            $table->json('driver_risk_modifiers')->nullable()->comment('low, medium, high');
            
            // Territory modifiers (JSON)
            $table->json('territory_modifiers')->nullable()->comment('urban, rural, offroad');
            
            // Trip type modifiers (JSON)
            $table->json('trip_type_modifiers')->nullable()->comment('commute, long_trip, offroad');
            
            // Usage frequency modifiers (JSON)
            $table->json('usage_frequency_modifiers')->nullable()->comment('lt_500, btw_500_1000, gt_1000');
            
            // Policy tenure modifiers (JSON)
            $table->json('policy_tenure_modifiers')->nullable()->comment('lt_2y, gt_2y, gt_5y');
            
            // Claims history modifiers (JSON)
            $table->json('claims_history_modifiers')->nullable()->comment('c0, c1, c2, c3_plus');
            
            // Gender factor
            $table->boolean('gender_enabled')->default(false);
            $table->json('gender_modifiers')->nullable()->comment('M, F, X');
            
            // Event penalty rates
            $table->decimal('speeding_penalty_per_event', 10, 2)->default(0.10)->comment('BWP per event');
            $table->decimal('harsh_acceleration_rate', 10, 2)->default(0.05)->comment('BWP/km');
            $table->decimal('idle_time_rate', 10, 2)->default(0.02)->comment('BWP/km');
            $table->decimal('high_revving_rate', 10, 2)->default(0.03)->comment('BWP/km');
            
            // Weather severity modifiers (JSON)
            $table->json('weather_severity_modifiers')->nullable()->comment('drizzle, rain, heavy_rain, thunderstorm');
            
            // Night segment modifiers (JSON)
            $table->json('night_segment_modifiers')->nullable()->comment('evening, late_night');
            
            // Other modifiers
            $table->decimal('congestion_modifier', 5, 4)->default(0.05)->comment('+2-5%');
            $table->decimal('seasonal_modifier', 5, 4)->default(0.05)->comment('+3-5% during rainy season');
            $table->decimal('adas_discount', 5, 4)->default(-0.05)->comment('-3 to -5%');
            $table->decimal('roadworthiness_discount', 5, 4)->default(-0.02)->comment('-2%');
            $table->decimal('safe_driver_cashback', 5, 4)->default(-0.03)->comment('-3% for OptiDrive ≥ 8.5');
            
            // Metadata
            $table->string('config_name')->default('Default Configuration');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppk_rating_config');
    }
};

