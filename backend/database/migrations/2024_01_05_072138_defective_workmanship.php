<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DefectiveWorkmanship extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('defective_workmanship', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('location_of_accident');
            $table->dateTime('accident_date_time');
            $table->string('owners_name');
            $table->string('telephone_number');
            $table->string('mobile_number');
            $table->text('address');
            $table->string('make');
            $table->string('model');
            $table->string('registration');
            $table->boolean('vehicle_drivable');
            $table->boolean('vehicle_handed_claimant')->nullable();
            $table->dateTime('when_vehicle_handed')->nullable();
            $table->text('allegations_received')->nullable();
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
        Schema::dropIfExists('defective_workmanship');
    }
}
