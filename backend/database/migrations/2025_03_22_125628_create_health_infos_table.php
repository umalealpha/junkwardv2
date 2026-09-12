<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHealthInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('health_infos', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->unique()->nullable();
            $table->integer('employees')->nullable();
            $table->string('cell_phone')->nullable();
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
        Schema::dropIfExists('health_infos');
    }
}
