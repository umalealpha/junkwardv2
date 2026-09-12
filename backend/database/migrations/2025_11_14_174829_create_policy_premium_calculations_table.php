<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyPremiumCalculationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_premium_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('policy_id')->index()->comment('Policy ID');
            $table->string('policy_number')->index()->comment('Policy Number');
            $table->unsignedInteger('vehicle_id')->nullable()->index()->comment('Vehicle ID');
            $table->string('vehicle_plate')->index()->comment('Vehicle Registration Plate');
            $table->unsignedTinyInteger('product_id')->nullable()->comment('Product ID');
            
            // Date Range
            $table->date('start_date')->index()->comment('Calculation Start Date');
            $table->date('end_date')->index()->comment('Calculation End Date');
            $table->unsignedSmallInteger('days_count')->comment('Number of Days');
            
            // Trip Data
            $table->unsignedInteger('trips_count')->comment('Number of Trips');
            $table->decimal('actual_distance_km', 12, 2)->comment('Actual Distance in KM');
            $table->decimal('billable_distance_km', 12, 2)->comment('Billable Distance in KM');
            $table->unsignedSmallInteger('distance_range_min')->nullable()->comment('Min Distance Range');
            $table->unsignedSmallInteger('distance_range_max')->nullable()->comment('Max Distance Range');
            $table->string('distance_adjustment', 500)->nullable()->comment('Distance Adjustment Note');
            
            // Behaviour Analysis
            $table->char('behaviour_category', 1)->comment('Behaviour Category (A, B, C, D)');
            $table->decimal('average_optidrive', 8, 4)->comment('Average OptiDrive Indicator');
            $table->decimal('behaviour_surcharge_percentage', 5, 2)->comment('Behaviour Surcharge %');
            $table->boolean('behaviour_category_auto_calculated')->default(true)->comment('Auto-calculated from trips');
            
            // Premium Calculation
            $table->decimal('monthly_premium', 12, 2)->comment('Base Monthly Premium');
            $table->string('premium_source', 50)->comment('Premium Source (Policy Table/Rate API)');
            $table->decimal('base_rate_per_km', 10, 4)->comment('Base Rate per KM');
            $table->decimal('final_rate_per_km', 10, 4)->comment('Final Rate per KM (with surcharge)');
            $table->decimal('base_premium', 12, 2)->comment('Base Premium Amount');
            $table->decimal('surcharge_amount', 12, 2)->comment('Surcharge Amount');
            $table->decimal('total_premium', 12, 2)->comment('Total Premium Amount');
            
            // Additional Info
            $table->text('calculation_breakdown')->nullable()->comment('JSON: Detailed Calculation Steps');
            $table->unsignedInteger('calculated_by')->nullable()->comment('User ID who triggered calculation');
            
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['policy_id', 'start_date', 'end_date']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_premium_calculations');
    }
}
