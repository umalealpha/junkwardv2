<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motor', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_coverage_id')->nullable();
            $table->string('vehicle_name')->nullable();
            $table->decimal('coverage_value',20,2)->nullable();
            $table->decimal('calculated_value',20,2)->nullable();
            $table->string('use')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('estimated_value')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable(); 
            $table->string('engine_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('tracking_device')->nullable();
            $table->string('security_features')->nullable();
            $table->string('type_of_cover')->nullable();
            $table->string('wreckage_removal')->nullable();
            $table->string('window_glass')->nullable();
            $table->string('locks_keys')->nullable();
            $table->string('parts_accessories')->nullable();
            $table->string('audio_accessories')->nullable();    
            $table->string('riot_strike')->nullable();
            $table->string('car_hire_theft')->nullable();
            $table->string('credit_shortfall')->nullable();
            $table->string('insured_driver')->nullable();
            $table->string('insured_family')->nullable();
            $table->string('medical_expenses')->nullable();
            $table->string('passenger_liability')->nullable();
            $table->string('third_party_liability')->nullable();
            $table->string('specified_accessories')->nullable();
            $table->decimal('premium_wreckage_removal',11,2)->nullable();
            $table->decimal('premium_window_glass',11,2)->nullable();
            $table->decimal('premium_locks_keys',11,2)->nullable();
            $table->decimal('premium_parts_accessories',11,2)->nullable();
            $table->decimal('premium_audio_accessories',11,2)->nullable();
            $table->decimal('premium_riot_strike',11,2)->nullable();
            $table->decimal('premium_car_hire_theft',11,2)->nullable();
            $table->decimal('premium_credit_shortfall',11,2)->nullable();
            $table->decimal('premium_insured_driver',11,2)->nullable();
            $table->decimal('premium_insured_family',11,2)->nullable();
            $table->decimal('premium_medical_expenses',11,2)->nullable();
            $table->decimal('premium_passenger_liability',11,2)->nullable();
            $table->decimal('premium_third_party_liability',11,2)->nullable();
            $table->decimal('premium_specified_accessories',11,2)->nullable();
            $table->string('own_damage')->nullable();
            $table->string('own_damage_minimun_percent')->nullable();
            $table->decimal('own_damage_minimum_amount',11,2)->nullable();
            $table->string('windscreen')->nullable();
            $table->string('windscreen_minimun_percent')->nullable();
            $table->decimal('windscreen_minimum_amount',11,2)->nullable();
            $table->string('loss_of_keys')->nullable();
            $table->string('loss_of_keys_minimun_percent')->nullable();
            $table->decimal('loss_of_keys_minimum_amount',11,2)->nullable();
            $table->string('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motor');
    }
}
