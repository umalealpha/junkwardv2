<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCarsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('policy_id')->unsigned()->nullable();
            $table->string('vehiclePlate')->nullable();
            $table->string('chassisNo')->nullable();
            $table->string('odometer')->nullable();
            $table->string('condition')->nullable();
            $table->string('purpose')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->longText('front')->nullable();
            $table->longText('back')->nullable();
            $table->longText('right')->nullable();
            $table->longText('left')->nullable();
            $table->string('vehicleRegistration')->nullable();
            $table->foreign('policy_id')->references('id')->on('policies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('cars');
    }
}
