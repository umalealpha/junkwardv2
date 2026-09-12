<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ear_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            
            // Policy Schedule fields
            $table->string('name_of_insured')->nullable();
            $table->string('site_of_erection')->nullable();
            
            // Section 1 - Material Damage
            $table->decimal('section1_total_sum_insured', 15, 2)->nullable();
            
            // Risk Coverage - Earthquake
            $table->string('risk_earthquake_covered')->nullable();
            $table->decimal('risk_earthquake_deductible', 15, 2)->nullable();
            $table->decimal('risk_earthquake_premium', 15, 2)->nullable();
            
            // Risk Coverage - Storm
            $table->string('risk_storm_covered')->nullable();
            $table->decimal('risk_storm_deductible', 15, 2)->nullable();
            $table->decimal('risk_storm_premium', 15, 2)->nullable();
            
            // Period of Insurance
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('weeks_of_testing')->nullable();
            
            // Endorsements
            $table->text('endorsement_1')->nullable();
            $table->text('endorsement_2')->nullable();
            $table->text('endorsement_3')->nullable();
            $table->text('endorsement_4')->nullable();
            
            // Total Premium
            $table->decimal('total_premium', 15, 2)->nullable();
            
            // Execution Details
            $table->string('executed_at')->nullable();
            $table->date('execution_date')->nullable();
            $table->string('signature')->nullable();
            
            // Additional Notes
            $table->text('additional_notes')->nullable();
            
            // Dynamic Section Items (JSON)
            $table->json('section1_items')->nullable();
            $table->json('section3_items')->nullable();
            
            $table->timestamps();
            
            // Index for performance
            $table->index('policy_id');
            $table->index('policy_coverage_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ear_coverages');
    }
}

