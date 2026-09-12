<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorTradersInternalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('motor_traders_internal')) {
            Schema::create('motor_traders_internal', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_coverage_id')->nullable();
                $table->string('type_of_cover')->nullable();
                $table->decimal('loss_or_damage_coverage_value',20,2)->nullable();
                $table->decimal('loss_or_damage_calculated_value',20,2)->nullable();
                $table->decimal('third_party_liability_coverage_value',20,2)->nullable();
                $table->decimal('third_party_liability_calculated_value',20,2)->nullable();
                $table->decimal('medical_benefits_coverage_value',20,2)->nullable();
                $table->decimal('medical_benefits_calculated_value',20,2)->nullable();
                $table->decimal('vehicle_lent_hire_coverage_value',20,2)->nullable();
                $table->decimal('vehicle_lent_hire_calculated_value',20,2)->nullable();
                $table->decimal('social_domestic_pleasure_coverage_value',20,2)->nullable();
                $table->decimal('social_domestic_pleasure_calculated_value',20,2)->nullable();
                $table->decimal('unauthoried_use_coverage_value',20,2)->nullable();
                $table->decimal('unauthoried_use_calculated_value',20,2)->nullable();
                $table->decimal('windscreen_coverage_value',20,2)->nullable();
                $table->decimal('windscreen_calculated_value',20,2)->nullable();
                $table->decimal('contigent_liability_coverage_value',20,2)->nullable();
                $table->decimal('contigent_liability_calculated_value',20,2)->nullable();
                $table->decimal('wreckage_removal_coverage_value',20,2)->nullable();
                $table->decimal('wreckage_removal_calculated_value',20,2)->nullable();
                $table->decimal('loss_of_key_coverage_value',20,2)->nullable();
                $table->decimal('loss_of_key_calculated_value',20,2)->nullable();
                $table->decimal('Loss_of_use_of_customer_coverage_value',20,2)->nullable();
                $table->decimal('Loss_of_use_of_customer_calculated_value',20,2)->nullable();
                $table->decimal('motor_cycle_motor_tricycle_coverage_value',20,2)->nullable();
                $table->decimal('motor_cycle_motor_tricycle_calculated_value',20,2)->nullable();
                $table->decimal('passanger_liability_respect_of_motor_coverage_value',20,2)->nullable();
                $table->decimal('passanger_liability_respect_of_motor_calculated_value',20,2)->nullable();
                $table->decimal('special_type_vehicle_coverage_value',20,2)->nullable();
                $table->decimal('special_type_vehicle_calculated_value',20,2)->nullable();
                $table->string('own_damage_minimun_percent')->nullable();
                $table->decimal('own_damage_minimum_amount',20,2)->nullable();
                $table->string('windscreen_minimun_percent')->nullable();
                $table->decimal('windscreen_minimum_amount',20,2)->nullable();
                $table->string('deleted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor_traders_external');
    }
}
