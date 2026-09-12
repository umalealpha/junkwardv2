<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VehicleDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_delete', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id')->nullable();
            $table->integer('customer_id')->nullable();
            $table->string('vehiclePlate')->nullable();
            $table->text('front')->nullable();
            $table->text('back')->nullable();
            $table->text('left')->nullable();
            $table->text('right')->nullable();
            $table->text('vehicle_registration')->nullable();
            $table->text('vehicle_valuation')->nullable();

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
        Schema::dropIfExists('vehicle_delete');
    }
}
