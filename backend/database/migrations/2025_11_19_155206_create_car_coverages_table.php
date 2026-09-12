<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('car_coverages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->unsignedBigInteger('policy_coverage_id')->nullable();
            $table->unsignedBigInteger('coverage_id')->nullable();
            
            // Policy Schedule fields
            $table->string('branch')->nullable();
            $table->string('policy_no')->nullable();
            $table->string('currency')->nullable();
            $table->string('declaration_no')->nullable();
            
            // Insured Information
            $table->string('insured_name')->nullable();
            $table->string('insured_street')->nullable();
            $table->string('insured_postal_code')->nullable();
            $table->string('title_of_contract')->nullable();
            
            // Risk Address
            $table->string('risk_street')->nullable();
            $table->string('risk_postal_code')->nullable();
            
            // Policy Dates
            $table->date('policy_inception_date')->nullable();
            $table->date('policy_expiry_date')->nullable();
            $table->date('today_date')->nullable();
            $table->string('new_altered')->nullable();
            $table->string('city_town_village')->nullable();
            
            // Section 1 - Material Damage
            $table->decimal('section1_contract_works_sum_insured', 15, 2)->nullable();
            $table->decimal('section1_contract_works_deductible', 15, 2)->nullable();
            $table->decimal('section1_contract_works_premium', 15, 2)->nullable();
            $table->decimal('section1_contract_price', 15, 2)->nullable();
            $table->decimal('section1_materials_supplied', 15, 2)->nullable();
            $table->decimal('section1_plant_equipment_sum_insured', 15, 2)->nullable();
            $table->decimal('section1_plant_equipment_deductible', 15, 2)->nullable();
            $table->decimal('section1_plant_equipment_premium', 15, 2)->nullable();
            $table->decimal('section1_machinery_sum_insured', 15, 2)->nullable();
            $table->decimal('section1_machinery_deductible', 15, 2)->nullable();
            $table->decimal('section1_machinery_premium', 15, 2)->nullable();
            $table->decimal('section1_clearance_debris_sum_insured', 15, 2)->nullable();
            $table->decimal('section1_clearance_debris_deductible', 15, 2)->nullable();
            $table->decimal('section1_clearance_debris_premium', 15, 2)->nullable();
            $table->decimal('section1_total_sum_insured', 15, 2)->nullable();
            
            // Section 1 - Risk Types
            $table->string('section1_risk_earthquake')->nullable();
            $table->decimal('section1_earthquake_deductible', 15, 2)->nullable();
            $table->decimal('section1_earthquake_premium', 15, 2)->nullable();
            $table->string('section1_risk_storm')->nullable();
            $table->decimal('section1_storm_deductible', 15, 2)->nullable();
            $table->decimal('section1_storm_premium', 15, 2)->nullable();
            
            // Section 2 - Third Party Liability
            $table->decimal('section2_bodily_injury_one_person', 15, 2)->nullable();
            $table->decimal('section2_bodily_injury_one_person_deductible', 15, 2)->nullable();
            $table->decimal('section2_bodily_injury_one_person_premium', 15, 2)->nullable();
            $table->decimal('section2_bodily_injury_total', 15, 2)->nullable();
            $table->decimal('section2_property_damage', 15, 2)->nullable();
            $table->decimal('section2_property_damage_deductible', 15, 2)->nullable();
            $table->decimal('section2_property_damage_premium', 15, 2)->nullable();
            
            // Section 3 - Principal's Loss of Profits
            $table->decimal('section3_annual_sum_insured', 15, 2)->nullable();
            $table->decimal('section3_sum_insured_max_indemnity', 15, 2)->nullable();
            $table->decimal('section3_premium', 15, 2)->nullable();
            $table->string('section3_period_insurance_from')->nullable();
            $table->string('section3_period_insurance_to')->nullable();
            $table->string('section3_maximum_indemnity')->nullable();
            $table->string('section3_time_excess')->nullable();
            
            // Section 56 - Contract Works
            $table->string('section56_item_no')->nullable();
            $table->text('section56_description')->nullable();
            $table->text('section56_loss_minimization')->nullable();
            
            // Endorsements
            $table->text('endorsement_1')->nullable();
            $table->text('endorsement_2')->nullable();
            $table->text('endorsement_3')->nullable();
            $table->text('endorsement_4')->nullable();
            
            // Notes
            $table->text('additional_notes')->nullable();
            
            // Execution Details
            $table->string('executed_at')->nullable();
            $table->date('execution_date')->nullable();
            $table->string('signature')->nullable();
            
            // Dynamic Section Items (JSON)
            $table->json('section1_items')->nullable();
            $table->json('section2_items')->nullable();
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
        Schema::dropIfExists('car_coverages');
    }
}
