<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateObdSchedulersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('obd_schedulers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('policy_id')->index();
            $table->string('policy_number')->index();
            $table->string('vehicle_plate')->index();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('distance_km', 12, 2)->default(0)->comment('Total distance traveled in km');
            $table->decimal('rate_per_km', 8, 2)->default(2.00)->comment('Rate per kilometer (P2/Km)');
            $table->decimal('calculated_premium', 12, 2)->default(0)->comment('Calculated monthly premium');
            $table->text('calculation_details')->nullable()->comment('JSON data with calculation breakdown');
            $table->timestamps();

            // Unique constraint to prevent duplicate entries for same policy and month
            $table->unique(['policy_id', 'year', 'month'], 'unique_policy_month');

            // Foreign key constraint (removed as policies table may not have proper indexes)
            // $table->foreign('policy_id')->references('id')->on('policies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('obd_schedulers');
    }
}

