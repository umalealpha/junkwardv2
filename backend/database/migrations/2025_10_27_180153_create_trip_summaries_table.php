<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTripSummariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trip_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('objectno')->index();
            $table->date('for_date')->index();
            $table->bigInteger('distance_m')->default(0);
            $table->integer('duration_s')->default(0);
            $table->integer('trips')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['objectno','for_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trip_summaries');
    }
}

