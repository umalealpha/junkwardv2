<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedVehicle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_vehicle', function(Blueprint $table)
        {
            $table->integer('id')->nullable(false);
            $table->string('customer_id')->nullable(true);
            $table->string('policy_id')->nullable(true);
            $table->string('vehiclePlate')->nullable(true);

            $table->string('estimated_value')->nullable(true);
            $table->string('mileage')->nullable(true);
            $table->string('chassisNo')->nullable(true);
            $table->string('odometer')->nullable(true);

            $table->string('condition')->nullable(true);
            $table->string('purpose')->nullable(true);
            $table->string('make')->nullable(true);
            $table->string('model')->nullable(true);

            $table->string('year')->nullable(true);
            $table->string('seats')->nullable(true);
            $table->string('cubic_capacity')->nullable(true);
            $table->string('cylinders')->nullable(true);

            $table->string('engineNo')->nullable(true);
            $table->string('is_private')->nullable(true);
            $table->string('is_imported')->nullable(true);
            $table->string('is_modified')->nullable(true);

            $table->string('is_tracking')->nullable(true);
            $table->string('type')->nullable(true);

            $table->string('front')->nullable(true);
            $table->string('back')->nullable(true);
            $table->string('right')->nullable(true);
            $table->string('left')->nullable(true);

            $table->string('vehicleRegistration')->nullable(true);
            $table->string('front_upload_date')->nullable(true);
            $table->string('back_upload_date')->nullable(true);
            $table->string('right_upload_date')->nullable(true);

            $table->string('left_upload_date')->nullable(true);
            $table->string('vehicleRegistration_upload_date')->nullable(true);
            $table->string('vinnumber')->nullable(true);
            $table->string('financial_interest')->nullable(true);

            $table->string('financial_interest_other')->nullable(true);
            $table->string('claim_count')->nullable(true);
            $table->string('vehicle_valuation')->nullable(true);
            $table->integer('compliance')->nullable(true);

            $table->string('created_at')->nullable(true);
            $table->string('updated_at')->nullable(true);

            $table->string('back_status')->nullable(true);
            $table->string('right_status')->nullable(true);
            $table->string('left_status')->nullable(true);
            $table->string('vehicle_registration_status')->nullable(true);
            $table->string('remark')->nullable(true);
            $table->string('front_image_remark')->nullable(true);
            $table->string('back_image_remark')->nullable(true);
            $table->string('left_image_remark')->nullable(true);
            $table->string('right_image_remark')->nullable(true);
            $table->string('registration_image_remark')->nullable(true);
            $table->string('reason')->nullable(true);
            $table->string('front_status')->nullable(true);
            $table->string('vehicle_registration')->nullable(true);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archived_vehicle');
    }
}
