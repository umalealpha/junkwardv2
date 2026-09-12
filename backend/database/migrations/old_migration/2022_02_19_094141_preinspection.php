<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Preinspection extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('preinspection', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->integer('customer_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('email')->nullable();
            $table->date('policy_activated_date')->nullable();
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
        Schema::dropIfExists('preinspection');
    }
}
