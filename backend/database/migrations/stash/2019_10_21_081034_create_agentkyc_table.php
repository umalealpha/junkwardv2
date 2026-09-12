<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAgentkycTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agentkyc', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('omangNumber')->nullable();
            $table->string('passportNumber')->nullable();
            $table->string('omang')->nullable();
            $table->string('proofResidence')->nullable();
            $table->string('driversLicense')->nullable();
            $table->string('proofIncome')->nullable();
            $table->string('passport')->nullable();
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
        Schema::dropIfExists('agentkyc');
    }
}
