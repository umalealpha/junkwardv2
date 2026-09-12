<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRiskAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('risk_address', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->nullable();
            $table->integer('policy_id')->nullable();
            $table->string('address_name')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->string('physical_address')->nullable();
            $table->string('risk_state')->nullable();
            $table->string('risk_city')->nullable();
            $table->string('extension')->nullable();
            $table->string('occupation')->nullable();
            $table->string('town_class')->nullable();
            $table->string('risk_class')->nullable();
            $table->string('iso_rcv')->nullable();
            $table->string('year_built')->nullable();
            $table->string('area')->nullable();
            $table->string('structure_type')->nullable();
            $table->string('const_type')->nullable();
            $table->string('distance_to_water')->nullable();
            $table->string('distance_to_fire')->nullable();
            $table->string('distance_to_hydrant')->nullable();
            $table->string('usage')->nullable();
            $table->string('occupancy_type')->nullable();
            $table->string('central_fire')->nullable();
            $table->string('central_burglar')->nullable();
            $table->string('gated_community')->nullable();
            $table->string('automatic')->nullable();
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
        Schema::dropIfExists('risk_address');
    }
}
