<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebfleetObjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('webfleet_objects', function (Blueprint $table) {
            $table->id();
            $table->string('objectno')->unique();
            $table->string('objectname')->nullable();
            $table->string('objecttype')->nullable();
            $table->string('drivername')->nullable();
            $table->bigInteger('odometer_long')->nullable();
            $table->json('raw_payload')->nullable();
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
        Schema::dropIfExists('webfleet_objects');
    }
}

