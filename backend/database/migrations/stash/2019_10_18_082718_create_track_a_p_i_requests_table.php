<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrackAPIRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('track_a_p_i_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->string('input')->nullable();
            $table->string('output')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('ajax_call')->nullable();
            $table->text('start_time')->nullable();
            $table->text('end_time')->nullable();
            $table->string('duration')->nullable();
            $table->time('time')->nullable();
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
        Schema::dropIfExists('track_a_p_i_requests');
    }
}
