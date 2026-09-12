<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIncidentPhotos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incident_photos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('claim_id')->unsigned()->nullable();
            $table->string('front')->nullable();
            $table->string('back')->nullable();
            $table->string('left')->nullable();
            $table->string('right')->nullable();
            $table->foreign('claim_id')->references('id')->on('glass_claims')->onDelete('cascade');
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
        Schema::dropIfExists('incident_photos');
    }
}
