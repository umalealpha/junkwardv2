<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTripsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('ext_trip_id')->unique(); // tripid from payload
            $table->string('objectno')->index();
            $table->string('objectname')->nullable();
            $table->string('objectuid')->nullable();
            $table->string('tripmode')->nullable();
            
            // Time data
            $table->timestampTz('start_time')->nullable();
            $table->timestampTz('end_time')->nullable();
            $table->integer('duration_s')->nullable(); // duration from payload
            $table->integer('idle_time')->nullable();
            
            // Location data (coordinates in microdegrees)
            $table->bigInteger('start_lat')->nullable(); // start_latitude
            $table->bigInteger('start_lon')->nullable(); // start_longitude
            $table->bigInteger('end_lat')->nullable(); // end_latitude
            $table->bigInteger('end_lon')->nullable(); // end_longitude
            $table->string('start_postext')->nullable();
            $table->string('end_postext')->nullable();
            
            // Distance and speed data
            $table->bigInteger('start_odometer')->nullable();
            $table->bigInteger('end_odometer')->nullable();
            $table->bigInteger('distance_m')->nullable(); // distance from payload
            $table->integer('avg_speed')->nullable();
            $table->integer('max_speed')->nullable();
            
            // Driver data
            $table->string('driverno')->nullable();
            $table->string('drivername')->nullable();
            $table->string('driveruid')->nullable();
            
            // Vehicle data
            $table->integer('fueltype')->nullable();
            
            // Performance indicators
            $table->decimal('optidrive_indicator', 5, 3)->nullable();
            $table->decimal('speeding_indicator', 5, 3)->nullable();
            $table->decimal('drivingevents_indicator', 5, 3)->nullable();
            $table->decimal('idling_indicator', 5, 3)->nullable();
            $table->decimal('constant_speed_indicator', 5, 3)->nullable();
            $table->decimal('high_revving_indicator', 5, 3)->nullable();
            
            // Store complete raw payload for reference
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            
            // Indexes for common queries
            $table->index('start_time');
            $table->index('driverno');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trips');
    }
}

