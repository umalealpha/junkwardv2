<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAgentpreinspectionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agentpreinspection', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('vehiclePlate')->nullable();
            $table->string('front')->nullable();
            $table->string('back')->nullable();
            $table->string('left')->nullable();
            $table->string('right')->nullable();
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
        Schema::dropIfExists('agentpreinspection');
    }
}
